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


async def load_system_settings() -> dict[str, str]:
    """system_settings 테이블에서 설정 정보를 로드하여 딕셔너리로 반환합니다."""
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
    return settings

