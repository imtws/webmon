"""
DB 연결 헬퍼
------------
aiomysql 기반 비동기 커넥션 풀.
각 엔진에서 `async with get_pool() as pool:` 로 사용합니다.
"""

import os
import aiomysql
from contextlib import asynccontextmanager

DB_CONFIG = dict(
    host   = os.environ.get("DB_HOST", "db"),
    port   = int(os.environ.get("DB_PORT", 3306)),
    user   = os.environ.get("DB_USER", "sitemonitor"),
    password = os.environ.get("DB_PASS", ""),
    db     = os.environ.get("DB_NAME", "sitemonitor"),
    charset  = "utf8mb4",
    autocommit = True,
)


@asynccontextmanager
async def get_pool(minsize: int = 1, maxsize: int = 10):
    pool = await aiomysql.create_pool(minsize=minsize, maxsize=maxsize, **DB_CONFIG)
    try:
        yield pool
    finally:
        pool.close()
        await pool.wait_closed()
