"""
Asset Engine
------------
PHP asset_engine.php 의 Python asyncio 변환본.

동작:
  1. ssl_mon=1 → ssl 인증서 만료일 갱신 (aiohttp + ssl cert)
  2. dom_mon=1 → WHOIS 조회로 도메인 만료일 갱신
  3. IP 주소 형식 URL 제외
  4. 한국 2단계 TLD (co.kr, go.kr 등) 처리
  5. WHOIS rate-limit 대응: 도메인당 1.2초 간격
"""

import asyncio
import logging
import os
import re
import ssl
import time
from datetime import datetime

import aiomysql
import aiohttp

from .db import get_pool, load_system_settings
from .process import communicate_with_timeout

log = logging.getLogger("webmon.asset")

# WHOIS 실행 경로
WHOIS_BIN = "/usr/bin/whois"

# 한국 2단계 TLD 목록
KR_TLDS = {
    "go", "or", "re", "ac", "ne", "ms", "hs", "sc", "co", "pe",
    "seoul", "busan", "daegu", "incheon", "gwangju", "daejeon",
    "ulsan", "gyeonggi", "gangwon", "chungbuk", "chungnam",
    "jeonbuk", "jeonnam", "gyeongbuk", "gyeongnam", "jeju",
}

# IP 주소 패턴 (URL 내 IP 포함 여부 감지)
IP_PATTERN = re.compile(r"\d+\.\d+\.\d+")

# WHOIS 만료일 파싱 패턴
WHOIS_EXPIRY_RE = re.compile(
    r"(?:사용 종료일|Expiration Date|Registry Expiry Date|Expires on|Expiry date)"
    r"\s*[:\s]*([\d.\-\s/T]{10,})",
    re.IGNORECASE,
)


# ── 유틸 ──────────────────────────────────────────────────────────────

def extract_host(url: str) -> str | None:
    m = re.match(r"https?://([^/:?#]+)", url, re.IGNORECASE)
    return m.group(1) if m else None


def to_lookup_domain(host: str) -> str:
    """서브도메인 제거 + 한국 2단계 TLD 처리."""
    host = re.sub(r"^(www\d*|m)\.", "", host, flags=re.IGNORECASE)
    parts = host.split(".")
    n = len(parts)
    if n >= 3 and parts[-1] == "kr" and parts[-2] in KR_TLDS:
        return ".".join(parts[-3:])
    return ".".join(parts[-2:]) if n >= 2 else host


def parse_whois_expiry(output: str) -> str | None:
    m = WHOIS_EXPIRY_RE.search(output)
    if not m:
        return None
    raw = m.group(1).strip()
    raw = re.sub(r"[\s.]+", "-", raw).rstrip("-")
    raw = re.sub(r"T.*$", "", raw)
    try:
        ts = datetime.strptime(raw[:10], "%Y-%m-%d")
        return ts.strftime("%Y-%m-%d")
    except ValueError:
        return None


def should_check_whois(expiry, last_check, now: datetime) -> bool:
    if not expiry or not last_check:
        return True
    try:
        if isinstance(expiry, str):
            expiry = datetime.strptime(expiry, "%Y-%m-%d").date()
        if isinstance(last_check, str):
            last_check = datetime.strptime(last_check, "%Y-%m-%d %H:%M:%S")
        return (expiry - now.date()).days <= 3 or (now - last_check).days >= 7
    except (ValueError, TypeError) as e:
        log.warning("[asset] WHOIS 조회 시각 판단 실패: %s", e)
        return True


# ── SSL 체크 ──────────────────────────────────────────────────────────

async def _check_ssl(host: str) -> str | None:
    """호스트의 SSL 인증서 만료일을 반환합니다."""
    # 1. Python standard SSL library (CERT_REQUIRED)
    try:
        ctx = ssl.create_default_context()
        reader, writer = await asyncio.wait_for(
            asyncio.open_connection(host, 443, ssl=ctx, server_hostname=host),
            timeout=10,
        )
        cert = writer.get_extra_info("ssl_object").getpeercert()
        writer.close()
        await writer.wait_closed()

        if cert and "notAfter" in cert:
            expiry = datetime.strptime(cert["notAfter"], "%b %d %H:%M:%S %Y %Z")
            return expiry.strftime("%Y-%m-%d")
    except Exception as e:
        log.debug(f"SSL CERT_REQUIRED 시도 실패 {host}: {e}")

    # 2. Fallback: openssl subprocess (인증서 만료 또는 Self-signed 등 대응)
    try:
        cmd = f"openssl s_client -connect {host}:443 -servername {host} -showcerts < /dev/null 2>/dev/null | openssl x509 -noout -enddate"
        proc = await asyncio.create_subprocess_shell(
            cmd,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.DEVNULL,
            start_new_session=True,
        )
        stdout, _ = await communicate_with_timeout(proc, timeout=10)
        output = stdout.decode("utf-8", errors="replace").strip()
        if output.startswith("notAfter="):
            raw_date = output.split("=", 1)[1].strip()
            expiry = datetime.strptime(raw_date, "%b %d %H:%M:%S %Y %Z")
            return expiry.strftime("%Y-%m-%d")
    except Exception as e:
        log.debug(f"SSL openssl fallback 실패 {host}: {e}")
    return None


# ── WHOIS 체크 ────────────────────────────────────────────────────────

async def _check_whois(domain: str) -> str | None:
    """WHOIS subprocess 로 도메인 만료일을 반환합니다."""
    try:
        proc = await asyncio.create_subprocess_exec(
            "timeout", "10", WHOIS_BIN, domain,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.DEVNULL,
            start_new_session=True,
        )
        stdout, _ = await communicate_with_timeout(proc, timeout=15)
        output = stdout.decode("utf-8", errors="replace")
        expiry = parse_whois_expiry(output)
        if not expiry:
            log.warning("[asset] WHOIS 만료일 조회 실패: %s (종료 코드 %s)", domain, proc.returncode)
        return expiry
    except Exception as e:
        log.warning("[asset] WHOIS 실패 %s: %s", domain, e)
    return None


# ── 메인 ──────────────────────────────────────────────────────────────

async def run_asset():
    log.info("[asset] ▶ 시작")
    t0 = time.monotonic()

    async with get_pool() as pool:
        async with pool.acquire() as conn:
            async with conn.cursor(aiomysql.DictCursor) as cur:
                await cur.execute("""
                    SELECT no, url, ssl_mon, dom_mon, company, dom_expiry, last_whois_check_at
                    FROM monitor_conf
                    WHERE (ssl_mon = 1 OR dom_mon = 1)
                      AND url NOT REGEXP '[0-9]+\\\\.[0-9]+\\\\.[0-9]+'
                """)
                rows = await cur.fetchall()

        domain_cache: dict[str, str | None] = {}
        updated = 0
        for row in rows:
            url  = row["url"]
            host = extract_host(url)
            if not host:
                continue

            updates: dict[str, str] = {}

            # ── SSL ────────────────────────────────────────────────
            if row["ssl_mon"] and url.lower().startswith("https://"):
                expiry = await _check_ssl(host)
                if expiry:
                    updates["ssl_expiry"] = expiry

            # ── WHOIS ──────────────────────────────────────────────
            if row["dom_mon"]:
                domain = to_lookup_domain(host)
                
                # WHOIS 조회 스킵 판단 (차단 방지 필터링)
                if should_check_whois(row.get("dom_expiry"), row.get("last_whois_check_at"), datetime.now()):
                    if domain in domain_cache:
                        expiry = domain_cache[domain]
                    else:
                        expiry = await _check_whois(domain)
                        domain_cache[domain] = expiry
                        await asyncio.sleep(1.5)   # rate-limit 방지 간격 약간 증가 (1.2s -> 1.5s)

                    if expiry:
                        updates["dom_expiry"] = expiry

            # ── DB 저장 ────────────────────────────────────────────
            if updates:
                set_clause = ", ".join(f"{k}=%s" for k in updates) + ", last_asset_check_at=NOW(), mod_date=NOW()"
                if "dom_expiry" in updates:
                    set_clause += ", last_whois_check_at=NOW()"
                async with pool.acquire() as conn:
                    async with conn.cursor() as cur:
                        await cur.execute(
                            f"UPDATE monitor_conf SET {set_clause} WHERE no=%s",
                            (*updates.values(), row["no"]),
                        )
                log.info(f"[asset] [{row['company']}] 업데이트 ({host}) → {updates}")
                updated += 1

    # ── Retention Clean-up (오래된 로그 정기 삭제) ──────────────────────
    try:
        settings = await load_system_settings()
        retention_days = int(settings.get("retention_days", "30") or "30")
        
        async with get_pool() as pool:
            async with pool.acquire() as conn:
                async with conn.cursor() as cur:
                    deleted = await cur.execute(
                        "DELETE FROM monitor_log WHERE log_time < DATE_SUB(NOW(), INTERVAL %s DAY)",
                        (retention_days,)
                    )
        log.info(f"[asset] 🧹 Retention Clean-up 완료 ({retention_days}일 경과 데이터 {deleted}건 삭제)")
    except Exception as e:
        log.error(f"[asset] 🧹 Retention Clean-up 실패: {e}")

    elapsed = time.monotonic() - t0
    log.info(f"[asset] ✅ 완료 ({elapsed:.1f}s, {updated}/{len(rows)}개 업데이트)")
