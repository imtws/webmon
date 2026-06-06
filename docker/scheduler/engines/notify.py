"""
알림 발송 모듈
--------------
텔레그램 sendMessage API 호출.
"""

import os
import logging
import aiohttp

log = logging.getLogger("webmon.notify")


async def send_telegram(message: str) -> bool:
    """텔레그램으로 메시지를 발송합니다. 실패 시 False 반환."""
    # 환경변수를 호출 시점에 읽어야 Docker 주입값이 정상 반영됨
    tg_token  = os.environ.get("TG_TOKEN", "")
    tg_chat_id = os.environ.get("TG_CHAT_ID", "")

    if not tg_token or not tg_chat_id:
        log.warning("TG_TOKEN 또는 TG_CHAT_ID 미설정 — 텔레그램 발송 건너뜀")
        return False

    url = f"https://api.telegram.org/bot{tg_token}/sendMessage"
    payload = {"chat_id": tg_chat_id, "text": message}

    try:
        async with aiohttp.ClientSession() as session:
            async with session.post(url, data=payload, timeout=aiohttp.ClientTimeout(total=10)) as resp:
                if resp.status == 200:
                    return True
                body = await resp.text()
                log.error(f"텔레그램 발송 실패 HTTP {resp.status}: {body[:200]}")
                return False
    except Exception as e:
        log.error(f"텔레그램 발송 예외: {e}")
        return False
