"""
DB 연결 헬퍼
------------
aiomysql 기반 비동기 커넥션 풀.
각 엔진에서 `async with get_pool() as pool:` 로 사용합니다.
"""

import os
import aiomysql
from contextlib import asynccontextmanager

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
    # Check for custom password override
    custom_pass_file = "/app/config/db_pass_custom.php"
    if os.path.exists(custom_pass_file):
        try:
            with open(custom_pass_file, "r") as f:
                for line in f:
                    if "DB_PASSWORD_OVERRIDE:" in line:
                        parts = line.split("DB_PASSWORD_OVERRIDE:", 1)
                        if len(parts) > 1:
                            config["password"] = parts[1].strip()
                            break
        except Exception:
            pass
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


def load_env() -> dict[str, str]:
    env = {}
    path = "/app/.env"
    if not os.path.exists(path):
        return env
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
                        if (v.startswith('"') and v.endswith('"')) or (v.startswith("'") and v.endswith("'")):
                            v = v[1:-1]
                        env[k] = v
    except Exception:
        pass
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
        # DB가 준비 안되었거나 에러 시 빈 dict 반환
        pass
    finally:
        pool.close()
        await pool.wait_closed()

    # .env 병합
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

