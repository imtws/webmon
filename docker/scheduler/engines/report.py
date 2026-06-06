"""
Report Engine
-------------
PHP asset_report_engine.php 의 Python asyncio 변환본.

동작:
  30일 이내 SSL/도메인 만료 예정 항목을 DB에서 조회해
  텔레그램으로 주간 리포트를 발송합니다.
"""

import logging
import os
import time
from datetime import datetime, date

import aiomysql

from .db import get_pool
from .notify import send_telegram

log = logging.getLogger("webmon.report")

WEBMON_URL = os.environ.get("WEBMON_URL", "http://localhost:8080")


async def run_report():
    log.info("[report] ▶ 시작")
    t0 = time.monotonic()

    async with get_pool() as pool:
        async with pool.acquire() as conn:
            async with conn.cursor(aiomysql.DictCursor) as cur:
                await cur.execute("""
                    SELECT company, url, ssl_expiry, dom_expiry
                    FROM monitor_conf
                    WHERE `check` != 'disable'
                      AND (
                        (ssl_mon = 1 AND ssl_expiry IS NOT NULL
                            AND ssl_expiry <= DATE_ADD(NOW(), INTERVAL 30 DAY))
                        OR
                        (dom_mon = 1 AND dom_expiry IS NOT NULL
                            AND dom_expiry <= DATE_ADD(NOW(), INTERVAL 30 DAY))
                      )
                    ORDER BY LEAST(
                        IFNULL(ssl_expiry, '9999-12-31'),
                        IFNULL(dom_expiry, '9999-12-31')
                    ) ASC
                """)
                rows = await cur.fetchall()

    today = date.today()
    ssl_items = []
    dom_items = []

    for row in rows:
        if row["ssl_expiry"]:
            exp  = row["ssl_expiry"] if isinstance(row["ssl_expiry"], date) else datetime.strptime(str(row["ssl_expiry"])[:10], "%Y-%m-%d").date()
            days = (exp - today).days
            if days <= 30:
                ssl_items.append({
                    "company": row["company"],
                    "url":     row["url"],
                    "expiry":  exp.strftime("%Y-%m-%d"),
                    "days":    days,
                })

        if row["dom_expiry"]:
            exp  = row["dom_expiry"] if isinstance(row["dom_expiry"], date) else datetime.strptime(str(row["dom_expiry"])[:10], "%Y-%m-%d").date()
            days = (exp - today).days
            if days <= 30:
                dom_items.append({
                    "company": row["company"],
                    "url":     row["url"],
                    "expiry":  exp.strftime("%Y-%m-%d"),
                    "days":    days,
                })

    if not ssl_items and not dom_items:
        log.info("[report] 만료 예정 항목 없음")
        return

    lines = [
        "📋 [WebMon 주간 만료 예정 리포트]",
        f"기준일: {today.strftime('%Y-%m-%d')} (30일 이내 만료 항목)",
        "",
    ]

    if ssl_items:
        lines += [f"🔐 SSL 인증서 만료 예정 ({len(ssl_items)}건)", "-" * 40]
        for r in ssl_items:
            label = f"❌ 만료됨 ({abs(r['days'])}일 경과)" if r["days"] < 0 else f"⚠️ D-{r['days']}"
            lines += [f"{label} | {r['company']} | {r['expiry']}", f"  └ {r['url']}"]
        lines.append("")

    if dom_items:
        lines += [f"🌐 도메인 만료 예정 ({len(dom_items)}건)", "-" * 40]
        for r in dom_items:
            label = f"❌ 만료됨 ({abs(r['days'])}일 경과)" if r["days"] < 0 else f"⚠️ D-{r['days']}"
            lines += [f"{label} | {r['company']} | {r['expiry']}", f"  └ {r['url']}"]
        lines.append("")

    lines.append(f"🔗 {WEBMON_URL}")

    msg = "\n".join(lines)
    ok = await send_telegram(msg)
    total = len(ssl_items) + len(dom_items)

    elapsed = time.monotonic() - t0
    if ok:
        log.info(f"[report] ✅ 텔레그램 발송 완료 ({total}건, {elapsed:.1f}s)")
    else:
        log.warning(f"[report] ⚠️ 발송 실패 — 내용:\n{msg}")
