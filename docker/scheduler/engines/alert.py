"""
Alert Engine
------------
PHP alert_engine.php 의 Python asyncio 변환본.

동작:
  1. monitor_conf 에서 check != 'disable' 대상 전체 로드
  2. asyncio.gather 로 전체 동시 실행 (lynx 또는 curl subprocess)
  3. 실패 + retry_count > 1 인 건 1초 후 재시도
  4. DB monitor_log 테이블에 시계열 로그 인서트
  5. check = 'alarm' 이고 결과 0 인 건 텔레그램 및 이메일 발송 (무음 스케줄링 처리 적용)
"""

import asyncio
import html
import logging
from logging.handlers import RotatingFileHandler
import os
import re
import time
from datetime import datetime

import aiomysql

from .db import get_pool, load_system_settings
from .notify import send_telegram, send_email
from .asset import _check_whois, to_lookup_domain, extract_host
from .process import communicate_with_timeout

log = logging.getLogger("webmon.alert")

WEBMON_URL = os.environ.get("WEBMON_URL", "http://localhost:8080")
UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) WebMon/2.1"

# 동시 실행 상한 (sysctl fs.pipe 등 고려)
CONCURRENCY = int(os.environ.get("ALERT_CONCURRENCY", 60))

LEGACY_TELEGRAM_TEMPLATE = """🚨 [WebMon] 웹사이트 장애 발생 알림

📌 대상 업체: {company}
🔗 대상 URL: {url}
❌ 감지 실패 키워드: {keyword}
⏱️ 발생 시간: {time}
⚙️ 실행 엔진: {engine} ({response_time}s)

💡 권장 처리 내용:
{comment}

🖥️ 대시보드: {web_url}"""

DEFAULT_EMAIL_SUBJECT = "[WebSiteMonitor] 웹사이트 모니터링 알람 발생 {YYYY-MM-DD}"

DEFAULT_TELEGRAM_TEMPLATE = """[WebSiteMonitor 알람발생]

시간 : {time}

-------------------------- 감지 내용 --------------------------
{detections}
-------------------------- 권 장 처 리 -------------------------
{recommendations}
--------------------------------------------------------------

웹사이트 모니터링 URL : {web_url}"""


def _group_email_items(failed_items: list[dict]) -> dict:
    """Group failed items by identical delivery settings so each recipient gets one email."""
    groups: dict = {}
    for item in failed_items:
        row = item["row"]
        key = (
            row.get("email_mode", "none") or "none",
            row.get("sender_email", "") or "",
            row.get("receiver_email", "") or "",
            row.get("smtp_host", "") or "",
            row.get("smtp_port", 25),
            row.get("smtp_user", "") or "",
            row.get("smtp_pass", "") or "",
            row.get("smtp_secure", "none") or "none",
        )
        groups.setdefault(key, []).append(item)
    return groups


def _normalize_comment(text: str) -> str:
    """Turn literal backslash-n sequences into real line breaks and drop blank lines."""
    text = re.sub(r"\\+n", "\n", text.replace("\r\n", "\n"))
    return "\n".join(line.strip() for line in text.split("\n") if line.strip())


def render_telegram_alert_message(
    failed_items: list[dict], template: str, now: str, web_url: str, escape: bool = False
) -> str:
    """Render one message with shared header/footer and per-item details."""
    esc = (lambda v: html.escape(v, quote=True)) if escape else (lambda v: v)
    detections = []
    recommendations = []
    companies = []
    urls = []
    keywords = []
    comments = []
    engines = []
    response_times = []

    for no, item in enumerate(failed_items, 1):
        row = item["row"]
        company = esc(str(row.get("company", "")))
        url = esc(str(row.get("url", "")))
        keyword = esc(str(row.get("contents", "")))
        comment = _normalize_comment(str(row.get("comment", "") or "")) or "별도 권장 처리 내용 없음"
        comment = esc(comment)
        reason = esc(item.get("reason") or "")

        if reason:
            if "도메인 만료" in reason:
                keyword = f"도메인 만료 ({reason})"
                comment = esc("도메인 만료가 감지되었습니다. 도메인 등록 대행업체를 통해 도메인 만료 연장을 진행해 주시기 바랍니다.")
            else:
                keyword = f"{keyword} ({reason})"

        detections.append(
            f"| {no}. {company} 업체의 {url} 사이트내에 [{keyword}] 컨텐츠 감지 불가!!"
        )
        indent = "<br>\n|    " if escape else "\n|    "
        recommendations.append(f"| {no}. {comment.replace(chr(10), indent)}")
        if company and company not in companies:
            companies.append(company)
        if url and url not in urls:
            urls.append(url)
        keywords.append(f"{company}: {keyword}" if company else keyword)
        comments.append(comment)
        engine = str(row.get("engine_type") or "lynx").upper()
        if engine not in engines:
            engines.append(engine)
        response_times.append(f"{float(item['elapsed']):.3f}s")

    sep = "<br>\n" if escape else "\n"
    replacements = {
        "{detections}": sep.join(detections),
        "{recommendations}": sep.join(recommendations),
        "{company}": ", ".join(companies),
        "{url}": sep.join(urls),
        "{keyword}": sep.join(keywords),
        "{comment}": sep.join(comments),
        "{time}": esc(now),
        "{YYYY-MM-DD}": esc(now[:10]),
        "{engine}": ", ".join(engines),
        "{response_time}": sep.join(response_times),
        "{web_url}": esc(web_url),
    }
    return re.sub(
        r"\{[A-Za-z_-]+\}",
        lambda match: replacements.get(match.group(0), match.group(0)),
        template,
    )


# ── 무음 스케줄 판별 ──────────────────────────────────────────────────

def is_muted(settings: dict) -> bool:
    """DB 무음 스케줄 설정을 확인하여 현재 무음 상태인지 반환합니다."""
    if settings.get("mute_enabled", "0") != "1":
        return False
    try:
        start_str = settings.get("mute_start_time", "22:00").strip()
        end_str = settings.get("mute_end_time", "07:00").strip()

        now = datetime.now().time()
        start = datetime.strptime(start_str, "%H:%M").time()
        end = datetime.strptime(end_str, "%H:%M").time()

        if start <= end:
            return start <= now <= end
        else:  # 예: 22:00 ~ 07:00 (자정 걸침)
            return now >= start or now <= end
    except Exception as e:
        log.error(f"무음 시간 계산 실패: {e}")
        return False


# ── subprocess 실행 ────────────────────────────────────────────────────

async def _fetch(row: dict, sem: asyncio.Semaphore) -> dict:
    """단일 URL을 lynx 또는 curl 로 체크하고 결과 dict 반환."""
    async with sem:
        no       = row["no"]
        url      = row["url"]
        keyword  = row["contents"]
        engine   = row.get("engine_type") or "lynx"
        timeout  = max(1, int(row.get("timeout_sec") or 2))

        if engine == "curl":
            cmd = [
                "timeout", str(timeout),
                "curl", "-k", "-s", "-L",
                "-A", UA,
                "--connect-timeout", str(timeout),
                url,
            ]
        else:
            cmd = [
                "env", "FORCE_SSL_PROMPT=yes",
                "timeout", str(timeout),
                "lynx",
                "-source",
                f"-useragent={UA}",
                "-nolist",
                "-force_secure",
                f"-connect_timeout={timeout}",
                url,
            ]

        cmd_str = " ".join(cmd)
        t_start = time.monotonic()
        val = 0
        reason = None
        try:
            proc = await asyncio.create_subprocess_exec(
                *cmd,
                stdout=asyncio.subprocess.PIPE,
                stderr=asyncio.subprocess.DEVNULL,
                start_new_session=True,
            )
            stdout, _ = await communicate_with_timeout(proc, timeout + 2)
            body = stdout.decode("utf-8", errors="replace")
            
            # 도메인 만료 안내 페이지 키워드 검사
            expired_keywords = [
                "도메인 만료 안내",
                "사용기간이 만료",
                "서비스가 만료",
                "만료된 도메인",
                "존재하지 않는 도메인",
                "domain is expired",
                "domain expired",
                "expired.gabia.com",
                "gabia.com/expired",
                "parking.cafe24.com",
                "hosting.cafe24.com/expired",
                "whois.co.kr/expired",
                "가비아 도메인 만료",
                "호스팅 만료",
            ]
            
            is_expired_page = any(k in body for k in expired_keywords)
            if is_expired_page:
                val = 0
                reason = "도메인 만료 안내 페이지 감지"
                cmd_str = f"[만료 페이지 감지] {cmd_str}"
            else:
                val = 1 if keyword in body else 0
                if val == 0:
                    reason = f"[{keyword}] 컨텐츠 감지 불가"
        except asyncio.TimeoutError:
            log.warning(f"[alert] TIMEOUT {url}")
            reason = "타임아웃 발생"
        except Exception as e:
            log.warning(f"[alert] ERROR {url}: {e}")
            reason = f"에러 발생: {e}"

        elapsed = time.monotonic() - t_start
        return {"no": no, "val": val, "elapsed": elapsed, "row": row, "cmd_str": cmd_str, "reason": reason}


# ── DB 결과 저장 및 로그 적재 ──────────────────────────────────────────

async def _save_results(pool, results: list[dict]):
    """monitor_conf 업데이트 및 monitor_log 테이블에 시계열 저장."""
    sql_conf = "UPDATE monitor_conf SET value=%s, error_msg=%s, response_time=%s, last_checked_at=NOW(), mod_date=NOW() WHERE no=%s"
    sql_log = "INSERT INTO monitor_log (monitor_conf_no, req_url, response_time, result, cmd) VALUES (%s, %s, %s, %s, %s)"
    async with pool.acquire() as conn:
        async with conn.cursor() as cur:
            await cur.executemany(
                sql_conf,
                [(r["val"], r.get("reason") or "", round(r["elapsed"], 3), r["no"]) for r in results],
            )
            await cur.executemany(
                sql_log,
                [(r["no"], r["row"]["url"], round(r["elapsed"], 3), r["val"], r.get("cmd_str")) for r in results],
            )


# ── 텍스트 파일 로그 기록 ──────────────────────────────────────────────

def _write_log(results: list[dict], settings: dict = None):
    log_path = "/app/logs/alert_engine.log"
    os.makedirs(os.path.dirname(log_path), exist_ok=True)
    rotate_mb = 50
    try:
        if settings:
            try:
                rotate_mb = max(1, int(settings.get("log_rotate_mb", 50)))
            except (TypeError, ValueError):
                rotate_mb = 50
        handler = RotatingFileHandler(
            log_path,
            maxBytes=rotate_mb * 1024 * 1024,
            backupCount=4,
            encoding="utf-8",
        )
        try:
            for r in results:
                status = "OK" if r["val"] else "FAIL"
                message = (
                    f"[{datetime.now():%Y-%m-%d %H:%M:%S}] [{status}] "
                    f"{r['row']['url']} ({r['elapsed']:.3f}s)"
                )
                record = logging.LogRecord(
                    "webmon.engine", logging.INFO, log_path, 0, message, (), None
                )
                handler.emit(record)
        finally:
            handler.close()
    except Exception as e:
        log.warning(f"로그 파일 기록 실패: {e}")


# ── 메인 ──────────────────────────────────────────────────────────────

async def run_alert():
    log.info("[alert] ▶ 시작")
    t0 = time.monotonic()

    settings = await load_system_settings()

    async with get_pool() as pool:
        # 대상 로드
        async with pool.acquire() as conn:
            async with conn.cursor(aiomysql.DictCursor) as cur:
                await cur.execute(
                    "SELECT * FROM monitor_conf WHERE `check` != 'disable'"
                )
                targets = await cur.fetchall()

        # 개별 아이템 일시 무음 필터링
        active_targets = []
        today = datetime.now().date()
        
        for row in targets:
            if row.get("sched_enabled") == 1:
                mute_until = row.get("mute_until")
                if mute_until:
                    try:
                        if isinstance(mute_until, str):
                            mute_time = datetime.strptime(mute_until, "%Y-%m-%d %H:%M:%S")
                        else:
                            mute_time = mute_until
                        
                        if datetime.now() < mute_time:
                            log.debug(f"[alert] [{row['company']}] 일시 무음 적용 중 ({mute_until}까지) -> 모니터링 스킵")
                            continue
                    except Exception as e:
                        log.error(f"일시 무음 시간 계산 실패 ({row['company']}): {e}")
            active_targets.append(row)

        if not active_targets:
            log.info("[alert] 모니터링 대상 없음 (모두 스케줄 외 또는 무음)")
            return

        sem = asyncio.Semaphore(CONCURRENCY)

        # 1차 실행 (모든 대상에 대해 매분 웹 진단 실행)
        results = await asyncio.gather(*[_fetch(row, sem) for row in active_targets])
        
        # 사후 분석 및 실시간 연장 검증 (WHOIS 조회 차단 방지와 정탐 보장을 동시에 처리)
        for r in results:
            row = r["row"]
            # 1) 도메인 만료일이 지난 상황인데 웹 진단이 성공한 경우 -> 실시간 연장 여부 1회 확인
            if r["val"] == 1 and row.get("dom_mon") == 1:
                dom_expiry = row.get("dom_expiry")
                if dom_expiry:
                    if isinstance(dom_expiry, str):
                        try:
                            dom_expiry_date = datetime.strptime(dom_expiry, "%Y-%m-%d").date()
                        except:
                            dom_expiry_date = None
                    else:
                        dom_expiry_date = dom_expiry
                    
                    if dom_expiry_date and dom_expiry_date < today:
                        # 캐시 등으로 인한 지속적 val=1 유도 시 WHOIS 난발을 막기 위해 30분 쿨타임 적용
                        should_query = True
                        last_whois_check = row.get("last_whois_check_at")
                        if last_whois_check:
                            if isinstance(last_whois_check, str):
                                try:
                                    last_whois_check = datetime.strptime(last_whois_check, "%Y-%m-%d %H:%M:%S")
                                except:
                                    last_whois_check = None
                            
                            if last_whois_check and (datetime.now() - last_whois_check).total_seconds() < 600:
                                should_query = False
                        
                        if should_query:
                            # 사용자가 연장했는지 실시간 WHOIS 확인
                            host = extract_host(row["url"])
                            if host:
                                domain = to_lookup_domain(host)
                                log.info(f"[alert] [{row['company']}] 도메인 만료 상태에서 웹 요청 성공 감지! 실시간 WHOIS 검증 시작 ({domain})")
                                expiry = await _check_whois(domain)
                                
                                # Record the WHOIS check independently from monitoring and config timestamps.
                                async with pool.acquire() as conn:
                                    async with conn.cursor() as cur:
                                        await cur.execute(
                                            "UPDATE monitor_conf SET last_whois_check_at=NOW() WHERE no=%s",
                                            (row["no"],)
                                        )
                                
                                if expiry:
                                    try:
                                        new_expiry_date = datetime.strptime(expiry, "%Y-%m-%d").date()
                                        if new_expiry_date >= today:
                                            # 도메인 연장 성공적으로 확인됨! DB 업데이트
                                            async with pool.acquire() as conn:
                                                async with conn.cursor() as cur:
                                                    await cur.execute(
                                                        "UPDATE monitor_conf SET dom_expiry=%s WHERE no=%s",
                                                        (expiry, row["no"]),
                                                    )
                                            log.info(f"[alert] [{row['company']}] 도메인 연장 감지 및 DB 업데이트 완료 ({expiry})")
                                            r["row"]["dom_expiry"] = expiry
                                        else:
                                            # 여전히 만료 상태인 경우, 캐시 등으로 인한 임시 응답 오탐으로 간주하여 실패 처리
                                            r["val"] = 0
                                            r["reason"] = f"도메인 만료일 경과 (만료일: {dom_expiry})"
                                            r["cmd_str"] = f"[도메인 만료 강제 실패] {r['cmd_str']}"
                                    except Exception as e:
                                        log.error(f"[alert] 실시간 연장 반영 중 오류: {e}")
                                else:
                                    # WHOIS 조회 실패 시에도 일단 오탐 방지를 위해 실패 처리 유지
                                    r["val"] = 0
                                    r["reason"] = f"도메인 만료일 경과 (만료일: {dom_expiry})"
                                    r["cmd_str"] = f"[도메인 만료 강제 실패] {r['cmd_str']}"
                            else:
                                r["val"] = 0
                                r["reason"] = f"도메인 만료일 경과 (만료일: {dom_expiry})"
                                r["cmd_str"] = f"[도메인 만료 강제 실패] {r['cmd_str']}"
                        else:
                            # 쿨타임 진행 중에는 WHOIS 쿼리를 던지지 않고 기존 만료 상태에 기반해 실패 처리
                            r["val"] = 0
                            r["reason"] = f"도메인 만료일 경과 (만료일: {dom_expiry})"
                            r["cmd_str"] = f"[도메인 만료 강제 실패 - 쿨타임 적용중] {r['cmd_str']}"
            
            # 2) 웹 진단이 실패했고 도메인 만료일이 지난 경우 -> 원인을 도메인 만료로 라벨링
            elif r["val"] == 0 and row.get("dom_mon") == 1:
                dom_expiry = row.get("dom_expiry")
                if dom_expiry:
                    if isinstance(dom_expiry, str):
                        try:
                            dom_expiry_date = datetime.strptime(dom_expiry, "%Y-%m-%d").date()
                        except:
                            dom_expiry_date = None
                    else:
                        dom_expiry_date = dom_expiry
                    
                    if dom_expiry_date and dom_expiry_date < today:
                        r["reason"] = f"도메인 만료일 경과 (만료일: {dom_expiry})"
                        r["cmd_str"] = f"[도메인 만료] {r['cmd_str']}"

        await _save_results(pool, results)
        _write_log(results, settings)

        # 재시도 (실패 + retry_count > 1 + 도메인 만료가 아닌 경우)
        retry_targets = [
            r["row"] for r in results
            if r["val"] == 0 and int(r["row"].get("retry_count") or 1) > 1 and "도메인 만료" not in (r.get("reason") or "")
        ]
        if retry_targets:
            await asyncio.sleep(1)
            retry_results = await asyncio.gather(*[_fetch(row, sem) for row in retry_targets])
            await _save_results(pool, retry_results)
            _write_log(retry_results, settings)
            # final_results 에 재시도 결과 반영
            retry_map = {r["no"]: r for r in retry_results}
            results = [retry_map.get(r["no"], r) for r in results]

        # 알람 전송 필터링
        failed_items = [
            r for r in results
            if r["row"].get("check") == "alarm" and r["val"] == 0
        ]

        if failed_items:
            # 무음 판별
            if is_muted(settings):
                log.info("[alert] 🚨 장애가 감지되었으나, 무음 시간대 설정이 적용되어 알림을 건너뜁니다.")
                return

            now = datetime.now().strftime("%Y-%m-%d | %I:%M:%S%p").lower()
            web_url = settings.get("webmon_url", WEBMON_URL)
            alert_format = settings.get("alert_format", "plain")

            # Telegram messages are plain text and aggregate this check cycle.
            tg_template = settings.get("tg_template_plain") or DEFAULT_TELEGRAM_TEMPLATE
            if tg_template == LEGACY_TELEGRAM_TEMPLATE:
                tg_template = DEFAULT_TELEGRAM_TEMPLATE
            telegram_message = render_telegram_alert_message(
                failed_items, tg_template, now, web_url
            )
            telegram_sent = await send_telegram(telegram_message, settings)
            if settings.get("tg_enabled", "0") == "1" and not telegram_sent:
                log.error("[alert] Telegram notification delivery failed")

            # 2. 이메일 발송 (HTML vs Plain)
            email_attempted = 0
            email_sent = 0
            if alert_format == "html":
                default_email_tpl = """<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: 'Apple SD Gothic Neo', 'Malgun Gothic', sans-serif; background-color: #f8fafc; color: #1e293b; padding: 20px; margin: 0; -webkit-text-size-adjust: none; -ms-text-size-adjust: none;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
        <div style="background-color: #f8fafc; color: #18aee7; padding: 24px; text-align: center; border-bottom: 1px solid #e2e8f0;">
            <h1 style="margin: 0; font-size: 20px; font-weight: 800; color: #18aee7;">[WebMon] 웹사이트 장애 발생 알림</h1>
        </div>
        <div style="padding: 30px; background-color: #ffffff;">
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px; background-color: #ffffff;">
                <tr style="background-color: #ffffff;">
                    <th style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; text-align: left; background-color: #f8fafc; color: #64748b; font-size: 12px; font-weight: 800; width: 30%;">대상 업체</th>
                    <td style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; text-align: left; color: #0f172a; font-size: 13px; font-weight: 600; background-color: #ffffff;">{company}</td>
                </tr>
                <tr style="background-color: #ffffff;">
                    <th style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; text-align: left; background-color: #f8fafc; color: #64748b; font-size: 12px; font-weight: 800;">모니터링 URL</th>
                    <td style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; text-align: left; color: #18aee7; font-size: 13px; font-weight: 600; background-color: #ffffff;"><a href="{url}" target="_blank" style="color: #18aee7; text-decoration: none;">{url}</a></td>
                </tr>
                <tr style="background-color: #ffffff;">
                    <th style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; text-align: left; background-color: #f8fafc; color: #64748b; font-size: 12px; font-weight: 800;">검출 키워드</th>
                    <td style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; text-align: left; color: #0f172a; font-size: 13px; font-weight: 600; background-color: #ffffff;"><span style="color: #ef4444; background-color: #fee2e2; padding: 2px 6px; border-radius: 4px; font-weight: 800;">{keyword}</span> (감지 불가)</td>
                </tr>
                <tr style="background-color: #ffffff;">
                    <th style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; text-align: left; background-color: #f8fafc; color: #64748b; font-size: 12px; font-weight: 800;">발생 시간</th>
                    <td style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; text-align: left; color: #0f172a; font-size: 13px; font-weight: 600; background-color: #ffffff;">{time}</td>
                </tr>
                <tr style="background-color: #ffffff;">
                    <th style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; text-align: left; background-color: #f8fafc; color: #64748b; font-size: 12px; font-weight: 800;">실행 엔진</th>
                    <td style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; text-align: left; color: #0f172a; font-size: 13px; font-weight: 600; background-color: #ffffff;">{engine}</td>
                </tr>
                <tr style="background-color: #ffffff;">
                    <th style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; text-align: left; background-color: #f8fafc; color: #64748b; font-size: 12px; font-weight: 800;">응답 속도</th>
                    <td style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; text-align: left; color: #0f172a; font-size: 13px; font-weight: 600; background-color: #ffffff;">{response_time}s</td>
                </tr>
            </table>
            
            <div style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
                <h4 style="margin: 0 0 6px 0; color: #b45309; font-size: 13px; font-weight: 800;">권장 처리 가이드</h4>
                <p style="margin: 0; color: #78350f; font-size: 13px; line-height: 1.5;">{comment}</p>
            </div>
        </div>
        <div style="background-color: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #f1f5f9;">
            <a href="{web_url}" style="display: inline-block; background-color: #18aee7; color: #ffffff !important; text-decoration: none; padding: 10px 20px; font-size: 13px; font-weight: 800; border-radius: 8px; box-shadow: 0 2px 4px rgba(24,174,231,0.2);" target="_blank">대시보드 바로가기</a>
        </div>
    </div>
</body>
</html>"""
                email_tpl = settings.get("email_template_html") or default_email_tpl

            else:
                default_plain_tpl = "\n".join([
                    "[WebSiteMonitor 알람발생]",
                    "",
                    "시간 : {time}",
                    "",
                    "-------------------------- 감지 내용 --------------------------",
                    "{detections}",
                    "-------------------------- 권 장 처 리 -------------------------",
                    "{recommendations}",
                    "--------------------------------------------------------------",
                    "",
                    "웹사이트 모니터링 : {web_url}",
                ])
                email_tpl = settings.get("email_template_plain") or default_plain_tpl

            email_groups = _group_email_items(failed_items)
            for group_key, group_items in email_groups.items():
                (custom_email_mode, custom_sender, custom_receiver,
                 indiv_host, indiv_port, indiv_user, indiv_pass, indiv_secure) = group_key
                email_body = render_telegram_alert_message(
                    group_items, email_tpl, now, web_url, escape=(alert_format == "html")
                )
                email_subject = (
                    settings.get("email_subject") or DEFAULT_EMAIL_SUBJECT
                ).replace("{YYYY-MM-DD}", now[:10]).replace("{time}", now).replace("\r", " ").replace("\n", " ").strip()
                email_ok = await send_email(
                    subject=email_subject,
                    body=email_body,
                    settings=settings,
                    sender_email=custom_sender,
                    receiver_email=custom_receiver,
                    email_mode=custom_email_mode,
                    smtp_host=indiv_host,
                    smtp_port=indiv_port,
                    smtp_user=indiv_user,
                    smtp_pass=indiv_pass,
                    smtp_secure=indiv_secure,
                    alert_format="html" if alert_format == "html" else "plain",
                )
                if custom_email_mode != "none" and settings.get("email_enabled", "0") == "1":
                    email_attempted += 1
                    email_sent += int(email_ok)

            email_failed = email_attempted - email_sent
            if email_failed:
                log.error("[alert] Email delivery failed for %s of %s attempted notifications", email_failed, email_attempted)
            log.info(
                "[alert] Alert processing complete (Telegram: %s, Email: %s/%s sent)",
                "sent" if telegram_sent else ("failed" if settings.get("tg_enabled", "0") == "1" else "disabled"),
                email_sent,
                email_attempted
            )
        else:
            log.info("[alert] ✅ 이상 없음")

    elapsed = time.monotonic() - t0
    log.info(f"[alert] 완료 ({elapsed:.1f}s, {len(targets)}개 대상)")
