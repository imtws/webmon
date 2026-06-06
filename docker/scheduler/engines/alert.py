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
import os
import time
from datetime import datetime

import aiomysql

from .db import get_pool, load_system_settings
from .notify import send_telegram, send_email

log = logging.getLogger("webmon.alert")

WEBMON_URL = os.environ.get("WEBMON_URL", "http://localhost:8080")
UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) WebMon/2.1"

# 동시 실행 상한 (sysctl fs.pipe 등 고려)
CONCURRENCY = int(os.environ.get("ALERT_CONCURRENCY", 60))


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

        t_start = time.monotonic()
        val = 0
        try:
            proc = await asyncio.create_subprocess_exec(
                *cmd,
                stdout=asyncio.subprocess.PIPE,
                stderr=asyncio.subprocess.DEVNULL,
            )
            stdout, _ = await asyncio.wait_for(proc.communicate(), timeout=timeout + 2)
            body = stdout.decode("utf-8", errors="replace")
            val = 1 if keyword in body else 0
        except asyncio.TimeoutError:
            log.warning(f"[alert] TIMEOUT {url}")
        except Exception as e:
            log.warning(f"[alert] ERROR {url}: {e}")

        elapsed = time.monotonic() - t_start
        return {"no": no, "val": val, "elapsed": elapsed, "row": row}


# ── DB 결과 저장 및 로그 적재 ──────────────────────────────────────────

async def _save_results(pool, results: list[dict]):
    """monitor_conf 업데이트 및 monitor_log 테이블에 시계열 저장."""
    sql_conf = "UPDATE monitor_conf SET value=%s, response_time=%s, mod_date=NOW() WHERE no=%s"
    sql_log = "INSERT INTO monitor_log (monitor_conf_no, req_url, response_time, result) VALUES (%s, %s, %s, %s)"
    async with pool.acquire() as conn:
        async with conn.cursor() as cur:
            await cur.executemany(
                sql_conf,
                [(r["val"], round(r["elapsed"], 3), r["no"]) for r in results],
            )
            await cur.executemany(
                sql_log,
                [(r["no"], r["row"]["url"], round(r["elapsed"], 3), r["val"]) for r in results],
            )


# ── 텍스트 파일 로그 기록 ──────────────────────────────────────────────

def _write_log(results: list[dict]):
    log_path = "/app/logs/alert_engine.log"
    os.makedirs(os.path.dirname(log_path), exist_ok=True)
    try:
        if os.path.exists(log_path) and os.path.getsize(log_path) >= 50 * 1024 * 1024:
            os.rename(log_path, log_path + ".old")
        with open(log_path, "a") as f:
            for r in results:
                status = "OK" if r["val"] else "FAIL"
                f.write(
                    f"[{datetime.now():%Y-%m-%d %H:%M:%S}] [{status}] "
                    f"{r['row']['url']} ({r['elapsed']:.3f}s)\n"
                )
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

        # 개별 아이템 일시 무음 필터링 (datetime 기반)
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
            
        targets = active_targets

        if not targets:
            log.info("[alert] 모니터링 대상 없음 (모두 스케줄 외 또는 무음)")
            return

        sem = asyncio.Semaphore(CONCURRENCY)

        # 1차 실행
        results = await asyncio.gather(*[_fetch(row, sem) for row in targets])
        await _save_results(pool, results)
        _write_log(results)

        # 재시도 (실패 + retry_count > 1)
        retry_targets = [
            r["row"] for r in results
            if r["val"] == 0 and int(r["row"].get("retry_count") or 1) > 1
        ]
        if retry_targets:
            await asyncio.sleep(1)
            retry_results = await asyncio.gather(*[_fetch(row, sem) for row in retry_targets])
            await _save_results(pool, retry_results)
            _write_log(retry_results)
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

            now = datetime.now().strftime("%Y-%m-%d | %I:%M:%S%p")
            web_url = settings.get("webmon_url", WEBMON_URL)
            alert_format = settings.get("alert_format", "plain")

            # 1. 텔레그램 발송 (언제나 plain으로 모든 장애 항목 통합 발송)
            telegram_lines = []
            telegram_actions = []
            for item in failed_items:
                row = item["row"]
                telegram_lines.append(
                    f"| {row['company']} 업체의 {row['url']} 사이트내에 [{row['contents']}] 컨텐츠 감지 불가!!"
                )
                telegram_actions.append(
                    f"| {row.get('comment') or '별도 권장 처리 내용 없음'}"
                )

            tg_msg = "\n".join([
                "🚨 [WebSiteMonitor 알람발생]",
                "",
                f"시간 : {now}",
                "",
                "-------------------------- 감지 내용 --------------------------",
                "\n".join(telegram_lines),
                "-------------------------- 권 장 처 리 -------------------------",
                "\n".join(telegram_actions),
                "--------------------------------------------------------------",
                "",
                f"🔗 웹사이트 모니터링 : {web_url}",
            ])
            await send_telegram(tg_msg, settings)

            # 2. 이메일 발송 (HTML vs Plain)
            if alert_format == "html":
                default_email_tpl = """<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: 'Apple SD Gothic Neo', 'Malgun Gothic', sans-serif; background-color: #f8fafc; color: #1e293b; padding: 20px; margin: 0; -webkit-text-size-adjust: none; -ms-text-size-adjust: none;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
        <div style="background: linear-gradient(135deg, #ef4444 0%, #f97316 100%); color: #ffffff; padding: 24px; text-align: center;">
            <h1 style="margin: 0; font-size: 20px; font-weight: 800; color: #ffffff;">🚨 [WebMon] 웹사이트 장애 발생 알림</h1>
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
                <h4 style="margin: 0 0 6px 0; color: #b45309; font-size: 13px; font-weight: 800;">💡 권장 처리 가이드</h4>
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

                def render_template(template: str, row: dict, elapsed: float) -> str:
                    replacements = {
                        "{company}": str(row.get("company", "")),
                        "{url}": str(row.get("url", "")),
                        "{keyword}": str(row.get("contents", "")),
                        "{comment}": str(row.get("comment", "") or "별도 권장 처리 내용 없음"),
                        "{time}": str(now),
                        "{engine}": str(row.get("engine_type") or "lynx").upper(),
                        "{response_time}": f"{elapsed:.3f}",
                        "{web_url}": str(web_url)
                    }
                    rendered = template
                    for key, val in replacements.items():
                        rendered = rendered.replace(key, val)
                    return rendered

                for item in failed_items:
                    row = item["row"]
                    elapsed = item["elapsed"]
                    email_body = render_template(email_tpl, row, elapsed)
                    
                    custom_sender = row.get("sender_email", "") or ""
                    custom_receiver = row.get("receiver_email", "") or ""
                    custom_email_mode = row.get("email_mode", "none") or "none"

                    indiv_host = row.get("smtp_host", "") or ""
                    indiv_port = row.get("smtp_port", 25)
                    indiv_user = row.get("smtp_user", "") or ""
                    indiv_pass = row.get("smtp_pass", "") or ""
                    indiv_secure = row.get("smtp_secure", "none") or "none"

                    await send_email(
                        subject=f"🚨 [WebSiteMonitor] 장애 발생 알림 ({row['company']})",
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
                        alert_format="html"
                    )
            else:
                for item in failed_items:
                    row = item["row"]
                    email_body = "\n".join([
                        "🚨 [WebSiteMonitor 알람발생]",
                        "",
                        f"시간 : {now}",
                        "",
                        "-------------------------- 감지 내용 --------------------------",
                        f"| {row['company']} 업체의 {row['url']} 사이트내에 [{row['contents']}] 컨텐츠 감지 불가!!",
                        "-------------------------- 권 장 처 리 -------------------------",
                        f"| {row.get('comment') or '별도 권장 처리 내용 없음'}",
                        "--------------------------------------------------------------",
                        "",
                        f"🔗 웹사이트 모니터링 : {web_url}",
                    ])
                    custom_sender = row.get("sender_email", "") or ""
                    custom_receiver = row.get("receiver_email", "") or ""
                    custom_email_mode = row.get("email_mode", "none") or "none"

                    indiv_host = row.get("smtp_host", "") or ""
                    indiv_port = row.get("smtp_port", 25)
                    indiv_user = row.get("smtp_user", "") or ""
                    indiv_pass = row.get("smtp_pass", "") or ""
                    indiv_secure = row.get("smtp_secure", "none") or "none"

                    await send_email(
                        subject=f"🚨 [WebSiteMonitor] 장애 발생 알림 ({row['company']})",
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
                        alert_format="plain"
                    )

            log.info(f"[alert] ✅ {len(failed_items)}건 알람 발송 완료 (Telegram/Email 개별발송)")
        else:
            log.info("[alert] ✅ 이상 없음")

    elapsed = time.monotonic() - t0
    log.info(f"[alert] 완료 ({elapsed:.1f}s, {len(targets)}개 대상)")

