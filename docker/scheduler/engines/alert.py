"""
Alert Engine
------------
PHP alert_engine.php 의 Python asyncio 변환본.

동작:
  1. monitor_conf 에서 check != 'disable' 대상 전체 로드
  2. asyncio.gather 로 전체 동시 실행 (lynx 또는 curl subprocess)
  3. 실패 + retry_count > 1 인 건 1초 후 재시도
  4. check = 'alarm' 이고 결과 0 인 건 텔레그램 발송
"""

import asyncio
import logging
import os
import time
from datetime import datetime

import aiomysql

from .db import get_pool
from .notify import send_telegram

log = logging.getLogger("webmon.alert")

WEBMON_URL = os.environ.get("WEBMON_URL", "http://localhost:8080")
UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) WebMon/2.1"

# 동시 실행 상한 (sysctl fs.pipe 등 고려)
CONCURRENCY = int(os.environ.get("ALERT_CONCURRENCY", 60))


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


# ── DB 결과 저장 ──────────────────────────────────────────────────────

async def _save_results(pool, results: list[dict]):
    sql = "UPDATE monitor_conf SET value=%s, response_time=%s, mod_date=NOW() WHERE no=%s"
    async with pool.acquire() as conn:
        async with conn.cursor() as cur:
            await cur.executemany(
                sql,
                [(r["val"], round(r["elapsed"], 3), r["no"]) for r in results],
            )


# ── 로그 기록 ─────────────────────────────────────────────────────────

def _write_log(results: list[dict]):
    log_path = "/app/logs/alert_engine.log"
    os.makedirs(os.path.dirname(log_path), exist_ok=True)
    try:
        # 50MB 롤오버
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
        log.warning(f"로그 기록 실패: {e}")


# ── 메인 ──────────────────────────────────────────────────────────────

async def run_alert():
    log.info("[alert] ▶ 시작")
    t0 = time.monotonic()

    async with get_pool() as pool:
        # 대상 로드
        async with pool.acquire() as conn:
            async with conn.cursor(aiomysql.DictCursor) as cur:
                await cur.execute(
                    "SELECT * FROM monitor_conf WHERE `check` != 'disable'"
                )
                targets = await cur.fetchall()

        if not targets:
            log.info("[alert] 모니터링 대상 없음")
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

        # 알람 발송
        alert_lines = []
        action_lines = []
        for r in results:
            row = r["row"]
            if row.get("check") == "alarm" and r["val"] == 0:
                alert_lines.append(
                    f"| {row['company']} 업체의 {row['url']} 사이트내에 [{row['contents']}] 컨텐츠 감지 불가!!"
                )
                action_lines.append(
                    f"| {row.get('comment') or '별도 권장 처리 내용 없음'}"
                )

        if alert_lines:
            now = datetime.now().strftime("%Y-%m-%d | %I:%M:%S%p")
            msg = "\n".join([
                "🚨 [WebSiteMonitor 알람발생]",
                "",
                f"시간 : {now}",
                "",
                "-------------------------- 감지 내용 --------------------------",
                "\n".join(alert_lines),
                "-------------------------- 권 장 처 리 -------------------------",
                "\n".join(action_lines),
                "--------------------------------------------------------------",
                "",
                f"🔗 웹사이트 모니터링 : {WEBMON_URL}",
            ])
            await send_telegram(msg)
            log.info(f"[alert] ✅ {len(alert_lines)}건 알람 발송")
        else:
            log.info("[alert] ✅ 이상 없음")

    elapsed = time.monotonic() - t0
    log.info(f"[alert] 완료 ({elapsed:.1f}s, {len(targets)}개 대상)")
