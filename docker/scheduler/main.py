#!/usr/bin/env python3
"""
WebMon Scheduler — 통합 엔진 메인
----------------------------------
APScheduler가 3개 엔진을 직접 호출합니다.
PHP subprocess 없이 순수 Python asyncio로 동작합니다.
"""

import asyncio
import logging
import os
import sys
from apscheduler.schedulers.blocking import BlockingScheduler
from apscheduler.triggers.cron import CronTrigger

from engines.alert  import run_alert
from engines.asset  import run_asset
from engines.report import run_report
from engines.db import ensure_monitor_timestamp_schema

# ── 로깅 설정 ──────────────────────────────────────────────────────────
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S",
    stream=sys.stdout,
    force=True,
)
log = logging.getLogger("webmon")

# ── 스케줄 환경변수 ────────────────────────────────────────────────────
SCHEDULE_ALERT  = os.environ.get("SCHEDULE_ALERT",  "* * * * *")
SCHEDULE_ASSET  = os.environ.get("SCHEDULE_ASSET",  "0 1 * * *")
SCHEDULE_REPORT = os.environ.get("SCHEDULE_REPORT", "0 9 * * 1")


def cron_kwargs(expr: str) -> dict:
    keys = ["minute", "hour", "day", "month", "day_of_week"]
    parts = expr.strip().split()
    if len(parts) != 5:
        raise ValueError(f"잘못된 cron 표현식: {expr!r}")
    return dict(zip(keys, parts))


def job_alert():
    try:
        asyncio.run(run_alert())
    except Exception as e:
        log.error(f"[alert] 예외: {e}")

def job_asset():
    try:
        asyncio.run(run_asset())
    except Exception as e:
        log.error(f"[asset] 예외: {e}")

def job_report():
    try:
        asyncio.run(run_report())
    except Exception as e:
        log.error(f"[report] 예외: {e}")


if __name__ == "__main__":
    asyncio.run(ensure_monitor_timestamp_schema())
    log.info("=" * 55)
    log.info("  WebMon Scheduler 시작")
    log.info(f"  alert  스케줄: {SCHEDULE_ALERT}")
    log.info(f"  asset  스케줄: {SCHEDULE_ASSET}")
    log.info(f"  report 스케줄: {SCHEDULE_REPORT}")
    log.info("=" * 55)

    scheduler = BlockingScheduler(timezone="Asia/Seoul")
    scheduler.add_job(job_alert,  CronTrigger(**cron_kwargs(SCHEDULE_ALERT)),  id="alert")
    scheduler.add_job(job_asset,  CronTrigger(**cron_kwargs(SCHEDULE_ASSET)),  id="asset")
    scheduler.add_job(job_report, CronTrigger(**cron_kwargs(SCHEDULE_REPORT)), id="report")

    try:
        scheduler.start()
    except (KeyboardInterrupt, SystemExit):
        log.info("스케줄러 종료")
