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
import logging
from logging.handlers import RotatingFileHandler
import os
import re
import shlex
import zlib
import time
from urllib.parse import urljoin
from datetime import datetime

import aiomysql

from .db import get_pool, load_system_settings
from .notify import send_telegram, send_email
from .process import communicate_with_timeout
from .alert_format import (
    DEFAULT_HTML_TEMPLATE,
    DEFAULT_TEXT_TEMPLATE,
    describe_problem,
    describe_recovery,
    render_alert,
)

log = logging.getLogger("webmon.alert")

WEBMON_URL = os.environ.get("WEBMON_URL", "http://localhost:8080")
UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) WebMon/2.1"

# 동시 실행 상한 (sysctl fs.pipe 등 고려)
CONCURRENCY = int(os.environ.get("ALERT_CONCURRENCY", 60))

DEFAULT_EMAIL_SUBJECT = "[WebSiteMonitor] 웹사이트 모니터링 알람 발생 {YYYY-MM-DD}"
RECOVERY_EMAIL_SUBJECT = "[WebSiteMonitor] 웹사이트 복구 안내 {YYYY-MM-DD}"

DEFAULT_TEMPLATES = {
    "tg_template_plain": DEFAULT_TEXT_TEMPLATE,
    "email_template_plain": DEFAULT_TEXT_TEMPLATE,
    "email_template_html": DEFAULT_HTML_TEMPLATE,
}


TEMPLATE_VERSION = "12"


async def _ensure_templates(pool, settings: dict) -> None:
    """비어 있거나 이전 형식인 템플릿을 현재 기본 템플릿으로 채워 설정 화면에서 편집할 수 있게 한다."""
    outdated = settings.get("alert_template_ver") != TEMPLATE_VERSION
    for key, default in DEFAULT_TEMPLATES.items():
        current = settings.get(key) or ""
        if not outdated and ("{#problems}" in current or "{#recoveries}" in current):
            continue
        async with pool.acquire() as conn:
            async with conn.cursor() as cur:
                await cur.execute(
                    "INSERT INTO system_settings (key_name, key_value) VALUES (%s, %s) "
                    "ON DUPLICATE KEY UPDATE key_value=VALUES(key_value)",
                    (key, default),
                )
        settings[key] = default
        log.info("[alert] 템플릿 '%s'을(를) 기본 템플릿으로 설정했습니다.", key)
    if outdated:
        async with pool.acquire() as conn:
            async with conn.cursor() as cur:
                await cur.execute(
                    "INSERT INTO system_settings (key_name, key_value) VALUES ('alert_template_ver', %s) "
                    "ON DUPLICATE KEY UPDATE key_value=VALUES(key_value)",
                    (TEMPLATE_VERSION,),
                )
        settings["alert_template_ver"] = TEMPLATE_VERSION


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

STATUS_MARK = "__WEBMON_HTTP__"


def _decode_raw(raw: bytes, mime_header: bool) -> str:
    """lynx -mime_header 는 gzip/deflate 본문을 풀지 않으므로 직접 해제한 뒤 문자열로 변환한다."""
    if mime_header:
        head, sep, body = raw.partition(b"\r\n\r\n")
        if not sep:
            head, sep, body = raw.partition(b"\n\n")
        enc = re.search(rb"^content-encoding:\s*(\S+)", head, re.I | re.M)
        if sep and enc and enc.group(1).lower() in (b"gzip", b"x-gzip", b"deflate"):
            for wbits in (47, -15):
                try:
                    body = zlib.decompress(body, wbits)
                    break
                except zlib.error:
                    continue
        raw = head + sep + body
    return raw.decode("utf-8", errors="replace")


def _split_response(raw: str, engine: str):
    """응답에서 (본문, HTTP 상태코드)를 분리한다. 헤더는 키워드 검사 대상에서 제외한다."""
    if engine == "curl":
        head, sep, code = raw.rpartition(STATUS_MARK)
        code = code.strip()
        status = int(code) if sep and code.isdigit() and int(code) > 0 else None
        return (head[:-1] if head.endswith("\n") else head), status
    match = re.match(r"HTTP/\S+\s+(\d{3})", raw)
    if not match:
        return raw, None
    parts = re.split(r"\r?\n\r?\n", raw, maxsplit=1)
    return (parts[1] if len(parts) > 1 else ""), int(match.group(1))


async def _fetch(row: dict, sem: asyncio.Semaphore) -> dict:
    """단일 URL을 lynx 또는 curl 로 체크하고 결과 dict 반환."""
    async with sem:
        no       = row["no"]
        url      = row["url"]
        keyword  = row["contents"]
        engine   = row.get("engine_type") or "lynx"
        timeout  = max(1, int(row.get("timeout_sec") or 2))

        def build_cmd(target: str, plain: bool = False) -> list:
            if engine == "curl":
                return [
                    "timeout", str(timeout),
                    "curl", "-k", "-s", "-L", "--compressed",
                    "-A", UA,
                    "--connect-timeout", str(timeout),
                    "-w", "\n" + STATUS_MARK + "%{http_code}",
                    target,
                ]
            return [
                "env", "FORCE_SSL_PROMPT=yes",
                "timeout", str(timeout),
                "lynx",
                "-source",
                *([] if plain else ["-mime_header"]),
                f"-useragent={UA}",
                "-nolist",
                "-force_secure",
                f"-connect_timeout={timeout}",
                target,
            ]

        async def run_once(target: str, plain: bool = False):
            proc = await asyncio.create_subprocess_exec(
                *build_cmd(target, plain),
                stdout=asyncio.subprocess.PIPE,
                stderr=asyncio.subprocess.DEVNULL,
                start_new_session=True,
            )
            stdout, _ = await communicate_with_timeout(proc, timeout + 2)
            return proc.returncode, _decode_raw(stdout, engine != "curl" and not plain)

        cmd_str = " ".join(shlex.quote(c) for c in build_cmd(url))
        t_start = time.monotonic()
        val = 0
        reason = None
        cause = None
        empty_body = False
        status = None
        try:
            url0 = url
            returncode, raw = await run_once(url)
            body, status = _split_response(raw, engine)
            # lynx -mime_header 는 리다이렉트를 따라가지 않으므로 Location 을 직접 따라간다.
            hops = 0
            while engine != "curl" and status and 300 <= status < 400 and hops < 5:
                location = re.search(r"^location:\s*(\S+)", raw, re.I | re.M)
                if not location:
                    break
                url_next = urljoin(url, location.group(1))
                returncode, raw = await run_once(url_next)
                body, status = _split_response(raw, engine)
                url = url_next
                hops += 1
            if engine != "curl" and status and 300 <= status < 400:
                # 쿠키가 필요한 리다이렉트 루프: 쿠키를 처리하는 일반 모드로 본문을 다시 받는다.
                returncode, raw = await run_once(url0, plain=True)
                body = raw
            if returncode == 124:
                reason = "타임아웃 발생"
                cause = "timeout"
            elif keyword in body:
                val = 1
            elif not body.strip() and returncode != 0:
                reason = "접속 실패 (응답 없음)"
                cause = "connect"
            else:
                reason = f"[{keyword}] 컨텐츠 감지 불가"
                cause = "keyword"
                empty_body = not body.strip()
        except asyncio.TimeoutError:
            log.warning(f"[alert] TIMEOUT {url}")
            reason = "타임아웃 발생"
            cause = "timeout"
        except Exception as e:
            log.warning(f"[alert] ERROR {url}: {e}")
            reason = f"에러 발생: {e}"
            cause = "error"

        if cause == "timeout":
            status = None
        elapsed = time.monotonic() - t_start
        return {
            "no": no, "val": val, "elapsed": elapsed, "row": row, "cmd_str": cmd_str,
            "reason": reason, "cause": cause, "empty_body": empty_body, "timeout": timeout,
            "status": status,
        }


# ── DB 결과 저장 및 로그 적재 ──────────────────────────────────────────

async def _save_results(pool, results: list[dict]):
    """monitor_conf 업데이트 및 monitor_log 테이블에 시계열 저장."""
    sql_conf = (
        "UPDATE monitor_conf SET value=%s, error_msg=%s, response_time=%s, http_status=%s, "
        "fail_since=IF(%s=0, COALESCE(fail_since, NOW()), NULL), "
        "last_checked_at=NOW(), mod_date=NOW() WHERE no=%s"
    )
    sql_log = "INSERT INTO monitor_log (monitor_conf_no, req_url, response_time, result, cmd) VALUES (%s, %s, %s, %s, %s)"
    async with pool.acquire() as conn:
        async with conn.cursor() as cur:
            await cur.executemany(
                sql_conf,
                [(r["val"], r.get("reason") or "", round(r["elapsed"], 3), r.get("status"), r["val"], r["no"]) for r in results],
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
        await _ensure_templates(pool, settings)
        # 대상 로드
        async with pool.acquire() as conn:
            async with conn.cursor(aiomysql.DictCursor) as cur:
                await cur.execute(
                    "SELECT * FROM monitor_conf WHERE `check` != 'disable'"
                )
                targets = await cur.fetchall()

        # 개별 아이템 일시 무음 필터링
        active_targets = []
        
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

        try:
            concurrency = int(settings.get("alert_concurrency") or os.environ.get("ALERT_CONCURRENCY", 60))
        except (ValueError, TypeError):
            concurrency = 60
        concurrency = max(5, min(200, concurrency))
        sem = asyncio.Semaphore(concurrency)

        # 1차 실행 (모든 대상에 대해 매분 웹 진단 실행)
        results = await asyncio.gather(*[_fetch(row, sem) for row in active_targets])
        
        await _save_results(pool, results)
        _write_log(results, settings)

        # 재시도 (실패 + retry_count > 1)
        retry_targets = [
            r["row"] for r in results
            if r["val"] == 0 and int(r["row"].get("retry_count") or 1) > 1
        ]
        if retry_targets:
            await asyncio.sleep(1)
            retry_results = await asyncio.gather(*[_fetch(row, sem) for row in retry_targets])
            await _save_results(pool, retry_results)
            _write_log(retry_results, settings)
            # final_results 에 재시도 결과 반영
            retry_map = {r["no"]: r for r in retry_results}
            results = [retry_map.get(r["no"], r) for r in results]

        # 알람 전송 대상: 장애(Problem)와 직전 장애에서 회복된 항목(Recovery)
        alarm_results = [r for r in results if r["row"].get("check") == "alarm"]
        failed_items = [r for r in alarm_results if r["val"] == 0]
        recovered_items = [
            r for r in alarm_results
            if r["val"] == 1 and r["row"].get("value") == 0 and r["row"].get("fail_since")
        ]

        if failed_items or recovered_items:
            # 무음 판별
            if is_muted(settings):
                log.info("[alert] 🚨 알림 대상이 있으나, 무음 시간대 설정이 적용되어 알림을 건너뜁니다.")
                return

            now_dt = datetime.now()
            now = now_dt.strftime("%Y-%m-%d %H:%M:%S")
            web_url = settings.get("webmon_url", WEBMON_URL)
            alert_format = settings.get("alert_format", "plain")

            for r in failed_items:
                r["kind"] = "problem"
            for r in recovered_items:
                r["kind"] = "recovery"

            def _split(items):
                problems = [describe_problem(i) for i in items if i["kind"] == "problem"]
                recoveries = [describe_recovery(i, now_dt) for i in items if i["kind"] == "recovery"]
                return problems, recoveries

            # Telegram messages are plain text and aggregate this check cycle.
            tg_problems, tg_recoveries = _split(failed_items + recovered_items)
            telegram_message = render_alert(
                settings["tg_template_plain"], tg_problems, tg_recoveries, now, web_url, html_mode=False
            )
            telegram_sent = await send_telegram(telegram_message, settings)
            if settings.get("tg_enabled", "0") == "1" and not telegram_sent:
                log.error("[alert] Telegram notification delivery failed")

            # 2. 이메일 발송 (HTML vs Plain)
            email_attempted = 0
            email_sent = 0
            email_groups = _group_email_items(failed_items + recovered_items)
            for group_key, group_items in email_groups.items():
                (custom_email_mode, custom_sender, custom_receiver,
                 indiv_host, indiv_port, indiv_user, indiv_pass, indiv_secure) = group_key
                problems, recoveries = _split(group_items)
                email_body = render_alert(
                    settings["email_template_html" if alert_format == "html" else "email_template_plain"],
                    problems, recoveries, now, web_url, html_mode=(alert_format == "html"),
                )
                subject_tpl = (
                    (settings.get("email_subject") or DEFAULT_EMAIL_SUBJECT)
                    if problems else RECOVERY_EMAIL_SUBJECT
                )
                email_subject = (
                    subject_tpl.replace("{YYYY-MM-DD}", now[:10]).replace("{time}", now)
                    .replace("\r", " ").replace("\n", " ").strip()
                )
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
