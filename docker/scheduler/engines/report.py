"""
Report Engine
-------------
SSL 인증서 / 도메인 만료 예정 항목을 이메일(HTML)로만 안내합니다.
발송 요일/시각/기준일수/수신자는 system_settings 에서 읽습니다.
"""

import html
import logging
import os
import time
from datetime import datetime, date

import aiomysql

from .db import get_pool, load_system_settings
from .notify import send_email

log = logging.getLogger("webmon.report")

WEBMON_URL = os.environ.get("WEBMON_URL", "http://localhost:8080")

DEFAULT_REPORT_SUBJECT = "[WebSiteMonitor] SSL/도메인 만료 예정 안내 {YYYY-MM-DD}"


def _to_date(value) -> date | None:
    if not value:
        return None
    if isinstance(value, datetime):
        return value.date()
    if isinstance(value, date):
        return value
    try:
        return datetime.strptime(str(value)[:10], "%Y-%m-%d").date()
    except ValueError:
        return None


def should_run_report(settings: dict, now: datetime) -> bool:
    """현재 시각이 설정된 발송 요일/시각과 일치하는지 확인합니다."""
    if settings.get("report_enabled", "1") != "1":
        return False
    weekdays = {
        part.strip() for part in (settings.get("report_weekdays") or "1").split(",") if part.strip()
    }
    # 설정은 일=0 ... 토=6, datetime.weekday() 는 월=0 ... 일=6
    if str((now.weekday() + 1) % 7) not in weekdays:
        return False
    return now.strftime("%H:%M") == (settings.get("report_time") or "09:00")


def _expiry_cell(days: int | None, expiry: str | None) -> str:
    if days is None:
        return "<span style='color:#94a3b8;'>-</span>"
    if days <= 7:
        fg, bg = "#ef4444", "#fee2e2"
    elif days <= 14:
        fg, bg = "#b45309", "#fef3c7"
    else:
        fg, bg = "#64748b", "#f1f5f9"
    text = f"{abs(days)}일 경과" if days < 0 else f"D-{days}"
    return (
        f"<div style='font-size:13px; font-weight:600; color:#0f172a;'>{expiry}</div>"
        f"<span style='display:inline-block; margin-top:4px; padding:2px 6px; border-radius:4px; "
        f"font-size:11px; font-weight:800; color:{fg}; background-color:{bg};'>{text}</span>"
    )


def build_html_report(today: date, rows: list[dict], within_days: int, web_url: str) -> str:
    ssl_count = sum(1 for r in rows if r["ssl_days"] is not None)
    dom_count = sum(1 for r in rows if r["dom_days"] is not None)
    cell = "padding:12px 16px; border-bottom:1px solid #f1f5f9; text-align:left; vertical-align:top;"
    th = (
        "padding:12px 16px; border-bottom:1px solid #e2e8f0; text-align:left; background-color:#f8fafc; "
        "color:#64748b; font-size:12px; font-weight:800;"
    )

    body_rows = []
    for r in rows:
        group = html.escape(r["group_name"] or "")
        group_html = (
            f"<div style='font-size:11px; color:#94a3b8; margin-bottom:2px;'>#{group}</div>" if group else ""
        )
        body_rows.append(
            "<tr style='background-color:#ffffff;'>"
            f"<td style='{cell} width:38%;'>{group_html}"
            f"<div style='font-size:13px; font-weight:700; color:#0f172a;'>{html.escape(r['company'])}</div>"
            f"<a href='{html.escape(r['url'], quote=True)}' target='_blank' style='font-size:12px; color:#18aee7; "
            f"text-decoration:none; word-break:break-all;'>{html.escape(r['url'])}</a></td>"
            f"<td style='{cell} width:31%;'>{_expiry_cell(r['ssl_days'], r['ssl_expiry'])}</td>"
            f"<td style='{cell} width:31%;'>{_expiry_cell(r['dom_days'], r['dom_expiry'])}</td>"
            "</tr>"
        )

    safe_url = html.escape(web_url, quote=True)
    return (
        "<!DOCTYPE html><html><head><meta charset='utf-8'></head>"
        "<body style=\"font-family:'Apple SD Gothic Neo','Malgun Gothic',sans-serif; background-color:#f8fafc; "
        "color:#1e293b; padding:20px; margin:0;\">"
        "<div style='max-width:640px; margin:0 auto; background-color:#ffffff; border:1px solid #e2e8f0; "
        "border-radius:16px; overflow:hidden; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);'>"
        "<div style='background-color:#f8fafc; padding:24px; text-align:center; border-bottom:1px solid #e2e8f0;'>"
        "<h1 style='margin:0; font-size:20px; font-weight:800; color:#18aee7;'>[WebMon] SSL·도메인 만료 예정 안내</h1>"
        f"<p style='margin:8px 0 0; font-size:13px; color:#64748b;'>{today.strftime('%Y-%m-%d')} 기준, "
        f"{within_days}일 이내 만료 예정 {len(rows)}건 (SSL {ssl_count} / 도메인 {dom_count})</p>"
        "</div>"
        "<div style='padding:24px 30px;'>"
        "<table style='width:100%; border-collapse:collapse; background-color:#ffffff;'>"
        f"<thead><tr><th style='{th}'>대상 업체</th><th style='{th}'>SSL 만료일</th>"
        f"<th style='{th}'>도메인 만료일</th></tr></thead>"
        f"<tbody>{''.join(body_rows)}</tbody></table>"
        "</div>"
        "<div style='background-color:#f8fafc; padding:20px; text-align:center; border-top:1px solid #f1f5f9;'>"
        f"<a href='{safe_url}' target='_blank' style='display:inline-block; background-color:#18aee7; "
        "color:#ffffff !important; text-decoration:none; padding:10px 20px; font-size:13px; font-weight:800; "
        "border-radius:8px; box-shadow:0 2px 4px rgba(24,174,231,0.2);'>대시보드 바로가기</a>"
        "</div></div></body></html>"
    )


async def _load_rows(within_days: int) -> list[dict]:
    async with get_pool() as pool:
        async with pool.acquire() as conn:
            async with conn.cursor(aiomysql.DictCursor) as cur:
                await cur.execute(
                    """
                    SELECT group_name, company, url, ssl_mon, ssl_expiry, dom_mon, dom_expiry,
                           email_mode, sender_email, receiver_email,
                           smtp_host, smtp_port, smtp_user, smtp_pass, smtp_secure
                    FROM monitor_conf
                    WHERE `check` != 'disable'
                      AND (
                        (ssl_mon = 1 AND ssl_expiry IS NOT NULL
                            AND ssl_expiry <= DATE_ADD(CURDATE(), INTERVAL %s DAY))
                        OR
                        (dom_mon = 1 AND dom_expiry IS NOT NULL
                            AND dom_expiry <= DATE_ADD(CURDATE(), INTERVAL %s DAY))
                      )
                    ORDER BY LEAST(
                        IFNULL(ssl_expiry, '9999-12-31'),
                        IFNULL(dom_expiry, '9999-12-31')
                    ) ASC
                    """,
                    (within_days, within_days),
                )
                raw_rows = await cur.fetchall()

    today = date.today()
    rows = []
    for row in raw_rows:
        item = dict(row)
        for key, mon in (("ssl", "ssl_mon"), ("dom", "dom_mon")):
            exp = _to_date(row[f"{key}_expiry"]) if row[mon] else None
            days = (exp - today).days if exp else None
            if days is not None and days > within_days:
                exp, days = None, None
            item[f"{key}_expiry"] = exp.strftime("%Y-%m-%d") if exp else None
            item[f"{key}_days"] = days
        if item["ssl_days"] is not None or item["dom_days"] is not None:
            rows.append(item)
    return rows


async def run_report(force: bool = False):
    log.info("[report] 시작")
    t0 = time.monotonic()

    settings = await load_system_settings()
    if not force and settings.get("report_enabled", "1") != "1":
        log.info("[report] 만료 안내 발송이 꺼져 있습니다")
        return

    try:
        within_days = min(365, max(1, int(settings.get("report_within_days") or 30)))
    except ValueError:
        within_days = 30

    rows = await _load_rows(within_days)
    if not rows:
        log.info("[report] 만료 예정 항목 없음")
        return

    today = date.today()
    web_url = settings.get("webmon_url", WEBMON_URL)

    subject = (settings.get("report_subject") or DEFAULT_REPORT_SUBJECT).replace(
        "{YYYY-MM-DD}", today.strftime("%Y-%m-%d")
    ).replace("\r", " ").replace("\n", " ").strip()

    email_attempted = 0
    email_sent = 0
    if settings.get("email_enabled", "0") == "1":
        receiver = (settings.get("report_receiver_email") or "").strip()
        if receiver:
            # 지정 수신자가 있으면 전체 항목을 한 통으로 발송 (발송 방식은 전역 이메일 설정)
            groups = {("postfix", "", receiver, "", 25, "", "", "none"): rows}
        else:
            groups = {}
            for r in rows:
                key = (
                    r.get("email_mode") or "none",
                    r.get("sender_email") or "",
                    r.get("receiver_email") or "",
                    r.get("smtp_host") or "",
                    r.get("smtp_port") or 25,
                    r.get("smtp_user") or "",
                    r.get("smtp_pass") or "",
                    r.get("smtp_secure") or "none",
                )
                groups.setdefault(key, []).append(r)

        for (mode, sender, rcpt, host, port, user, pw, secure), items in groups.items():
            if mode == "none":
                continue
            email_attempted += 1
            email_sent += int(await send_email(
                subject=subject,
                body=build_html_report(today, items, within_days, web_url),
                settings=settings,
                sender_email=sender,
                receiver_email=rcpt,
                email_mode=mode,
                smtp_host=host,
                smtp_port=port,
                smtp_user=user,
                smtp_pass=pw,
                smtp_secure=secure,
                alert_format="html",
            ))

    log.info(
        "[report] 완료 (%s건, email=%s/%s, %.1fs)",
        len(rows),
        email_sent,
        email_attempted,
        time.monotonic() - t0,
    )
