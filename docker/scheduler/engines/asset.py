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

from .db import get_pool

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
        return ts.strftime("%Y-%m-%d 00:00:00")
    except ValueError:
        return None


# ── SSL 체크 ──────────────────────────────────────────────────────────

async def _check_ssl(host: str) -> str | None:
    """호스트의 SSL 인증서 만료일을 반환합니다."""
    try:
        ctx = ssl.create_default_context()
        ctx.check_hostname = False
        ctx.verify_mode = ssl.CERT_NONE

        reader, writer = await asyncio.wait_for(
            asyncio.open_connection(host, 443, ssl=ctx),
            timeout=10,
        )
        cert = writer.get_extra_info("ssl_object").getpeercert()
        writer.close()
        await writer.wait_closed()

        if cert and "notAfter" in cert:
            expiry = datetime.strptime(cert["notAfter"], "%b %d %H:%M:%S %Y %Z")
            return expiry.strftime("%Y-%m-%d %H:%M:%S")
    except Exception as e:
        log.debug(f"SSL 체크 실패 {host}: {e}")
    return None


# ── WHOIS 체크 ────────────────────────────────────────────────────────

async def _check_whois(domain: str) -> str | None:
    """WHOIS subprocess 로 도메인 만료일을 반환합니다."""
    try:
        proc = await asyncio.create_subprocess_exec(
            "timeout", "10", WHOIS_BIN, domain,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.DEVNULL,
        )
        stdout, _ = await asyncio.wait_for(proc.communicate(), timeout=15)
        output = stdout.decode("utf-8", errors="replace")
        return parse_whois_expiry(output)
    except Exception as e:
        log.debug(f"WHOIS 실패 {domain}: {e}")
    return None


# ── 메인 ──────────────────────────────────────────────────────────────

async def run_asset():
    log.info("[asset] ▶ 시작")
    t0 = time.monotonic()

    async with get_pool() as pool:
        async with pool.acquire() as conn:
            async with conn.cursor(aiomysql.DictCursor) as cur:
                await cur.execute("""
                    SELECT no, url, ssl_mon, dom_mon, company
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
                if domain in domain_cache:
                    expiry = domain_cache[domain]
                else:
                    expiry = await _check_whois(domain)
                    domain_cache[domain] = expiry
                    await asyncio.sleep(1.2)   # rate-limit

                if expiry:
                    updates["dom_expiry"] = expiry

            # ── DB 저장 ────────────────────────────────────────────
            if updates:
                set_clause = ", ".join(f"{k}=%s" for k in updates) + ", mod_date=NOW()"
                async with pool.acquire() as conn:
                    async with conn.cursor() as cur:
                        await cur.execute(
                            f"UPDATE monitor_conf SET {set_clause} WHERE no=%s",
                            (*updates.values(), row["no"]),
                        )
                log.info(f"[asset] [{row['company']}] 업데이트 ({host}) → {updates}")
                updated += 1

    elapsed = time.monotonic() - t0
    log.info(f"[asset] ✅ 완료 ({elapsed:.1f}s, {updated}/{len(rows)}개 업데이트)")
