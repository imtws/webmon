"""
DB 연결 헬퍼
------------
aiomysql 기반 비동기 커넥션 풀.
각 엔진에서 `async with get_pool() as pool:` 로 사용합니다.
"""

import ast
import logging
import os
import aiomysql
from contextlib import asynccontextmanager

log = logging.getLogger("webmon.db")

def get_db_config() -> dict:
    config = dict(
        host   = os.environ.get("DB_HOST", "db"),
        port   = int(os.environ.get("DB_PORT", 3306)),
        user   = os.environ.get("DB_USER", "sitemonitor"),
        password = os.environ.get("DB_PASS", ""),
        db     = os.environ.get("DB_NAME", "sitemonitor"),
        charset  = "utf8mb4",
        autocommit = True,
    )
    return config


@asynccontextmanager
async def get_pool(minsize: int = 1, maxsize: int = 10):
    config = get_db_config()
    pool = await aiomysql.create_pool(minsize=minsize, maxsize=maxsize, **config)
    try:
        yield pool
    finally:
        pool.close()
        await pool.wait_closed()


async def ensure_monitor_timestamp_schema() -> None:
    """Add and initialize timestamps needed to separate monitor activity types."""
    columns = {
        "updated_at": "DATETIME DEFAULT NULL COMMENT '설정 수정 시각'",
        "last_checked_at": "DATETIME DEFAULT NULL COMMENT '마지막 모니터링 실행 시각'",
        "last_asset_check_at": "DATETIME DEFAULT NULL COMMENT '마지막 SSL/도메인 정보 확인 시각'",
        "last_whois_check_at": "DATETIME DEFAULT NULL COMMENT '마지막 실시간 WHOIS 확인 시각'",
        "fail_since": "DATETIME DEFAULT NULL COMMENT '현재 장애 시작 시각'",
        "http_status": "SMALLINT DEFAULT NULL COMMENT '마지막 체크의 HTTP 상태코드'",
    }
    async with get_pool() as pool:
        async with pool.acquire() as conn:
            async with conn.cursor() as cur:
                for column, definition in columns.items():
                    await cur.execute(
                        f"ALTER TABLE monitor_conf ADD COLUMN IF NOT EXISTS `{column}` {definition}"
                    )
                await cur.execute(
                    "UPDATE monitor_conf SET updated_at=mod_date "
                    "WHERE updated_at IS NULL AND mod_date IS NOT NULL"
                )
                await cur.execute(
                    "UPDATE monitor_conf SET last_asset_check_at=mod_date "
                    "WHERE last_asset_check_at IS NULL AND mod_date IS NOT NULL"
                )
                await cur.execute(
                    "UPDATE monitor_conf c SET last_checked_at=("
                    "SELECT MAX(l.log_time) FROM monitor_log l WHERE l.monitor_conf_no=c.no"
                    ") WHERE c.last_checked_at IS NULL AND EXISTS ("
                    "SELECT 1 FROM monitor_log l WHERE l.monitor_conf_no=c.no)"
                )


def load_env() -> dict[str, str]:
    env = {}
    for path in ("/app/.env", "/var/lib/webmon/settings.env"):
        if not os.path.exists(path):
            continue
        try:
            with open(path, "r", encoding="utf-8") as f:
                for line in f:
                    line = line.strip()
                    if not line or line.startswith("#"):
                        continue
                    if "=" in line:
                        parts = line.split("=", 1)
                        if len(parts) == 2:
                            k = parts[0].strip()
                            v = parts[1].strip()
                            if v.startswith('"') and v.endswith('"'):
                                v = ast.literal_eval(v)
                            elif v.startswith("'") and v.endswith("'"):
                                v = v[1:-1]
                            env[k] = v
        except OSError:
            continue
    return env


async def load_system_settings() -> dict[str, str]:
    """system_settings 테이블에서 설정 정보를 로드하고 .env 설정과 병합하여 반환합니다."""
    settings = {}
    config = get_db_config()
    pool = await aiomysql.create_pool(minsize=1, maxsize=2, **config)
    try:
        async with pool.acquire() as conn:
            async with conn.cursor() as cur:
                await cur.execute("SELECT key_name, key_value FROM system_settings")
                rows = await cur.fetchall()
                for key_name, key_value in rows:
                    settings[key_name] = key_value if key_value is not None else ""
    except Exception as e:
        log.error("시스템 설정 로드 실패: %s", e)
    finally:
        pool.close()
        await pool.wait_closed()

    # Merge environment defaults with private runtime overrides.
    env = load_env()
    for k, v in env.items():
        if k == 'TG_TOKEN': settings['tg_token'] = v
        elif k == 'TG_CHAT_ID': settings['tg_chat_id'] = v
        elif k == 'SMTP_HOST': settings['smtp_host'] = v
        elif k == 'SMTP_PORT': settings['smtp_port'] = v
        elif k == 'SMTP_USER': settings['smtp_user'] = v
        elif k == 'SMTP_PASS': settings['smtp_pass'] = v
        elif k == 'SMTP_SECURE': settings['smtp_secure'] = v
        elif k == 'WEBMON_URL': settings['webmon_url'] = v

    return settings
