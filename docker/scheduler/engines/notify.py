"""
알림 발송 모듈
--------------
텔레그램 API 호출 및 SMTP(이메일 릴레이) 발송을 처리합니다.
설정값은 DB(system_settings)에서 동적으로 로드합니다.
"""

import logging
import os
import smtplib
import asyncio
from email.mime.text import MIMEText
from email.header import Header
import aiohttp

from .db import load_system_settings

log = logging.getLogger("webmon.notify")


async def send_telegram(message: str, settings: dict = None, parse_mode: str = "") -> bool:
    """텔레그램으로 메시지를 발송합니다. DB 설정을 기반으로 발송 여부를 판단합니다."""
    if settings is None:
        settings = await load_system_settings()

    tg_enabled = settings.get("tg_enabled", "0") == "1"
    tg_token = settings.get("tg_token", "")
    tg_chat_id = settings.get("tg_chat_id", "")

    if not tg_enabled:
        log.debug("텔레그램 알림 비활성화 상태")
        return False

    if not tg_token or not tg_chat_id:
        log.warning("텔레그램 활성화되었으나 TG_TOKEN 또는 TG_CHAT_ID 설정 누락")
        return False

    url = f"https://api.telegram.org/bot{tg_token}/sendMessage"
    payload = {"chat_id": tg_chat_id, "text": message}
    if parse_mode:
        payload["parse_mode"] = parse_mode

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


def _send_email_sync(subject: str, body: str, settings: dict, sender_email: str = "", receiver_email: str = "", email_mode: str = "none",
                     smtp_host: str = "", smtp_port: int = 25, smtp_user: str = "", smtp_pass: str = "", smtp_secure: str = "none",
                     alert_format: str = "plain") -> bool:
    """동기식 SMTP 발송 처리 (스레드에서 구동됨)"""
    # 이메일 발송 방식이 'none'인 경우 발송 안 함
    if email_mode == "none" or not email_mode:
        log.debug("이메일 알림 비활성화 상태 (email_mode == none)")
        return False

    # 개별 수신자가 설정되어 있으면 그것을 쓰고, 없으면 전역 수신자 사용
    alert_email = receiver_email.strip() if receiver_email and receiver_email.strip() else settings.get("alert_email", "")

    if not alert_email:
        log.warning("이메일 알림 수신 이메일(alert_email)이 입력되지 않음")
        return False

    # SMTP 모드 분기 (postfix vs smtp)
    if email_mode == "postfix":
        smtp_host = "mail"
        smtp_port = 25
        smtp_user = ""
        smtp_pass = ""
        smtp_secure = "none"
    elif email_mode == "smtp":
        # 개별 SMTP 설정이 존재하는 경우 우선 적용
        if smtp_host and smtp_host.strip():
            smtp_host = smtp_host.strip()
            smtp_port = int(smtp_port) if smtp_port else 25
            smtp_user = smtp_user.strip() if smtp_user else ""
            smtp_pass = smtp_pass.strip() if smtp_pass else ""
            smtp_secure = smtp_secure.strip() if smtp_secure else "none"
        else:
            # DB system_settings 에서 메일 설정을 로드 (직접 SMTP 릴레이)
            smtp_host = settings.get("smtp_host", "mail")
            try:
                smtp_port = int(settings.get("smtp_port", "25"))
            except ValueError:
                smtp_port = 25
                
            smtp_user = settings.get("smtp_user", "")
            smtp_pass = settings.get("smtp_pass", "")
            smtp_secure = settings.get("smtp_secure", "none")
    else:
        # 레거시 연동용 예외 처리
        smtp_host = "mail"
        smtp_port = 25
        smtp_user = ""
        smtp_pass = ""
        smtp_secure = "none"

    # 발송자 지정 우선순위:
    # 1. 개별 모니터링 항목에 설정된 발송자 (sender_email)
    # 2. SMTP Username에 이메일이 들어있으면 그것을 사용
    # 3. 그 외 기본값 webmon@localhost
    sender = "webmon@localhost"
    if sender_email and sender_email.strip():
        sender = sender_email.strip()
    elif smtp_user and "@" in smtp_user:
        sender = smtp_user

    subtype = "html" if alert_format == "html" else "plain"
    msg = MIMEText(body, subtype, "utf-8")
    msg["Subject"] = Header(subject, "utf-8")
    msg["From"] = sender
    msg["To"] = alert_email

    try:
        if smtp_secure == "ssl":
            server = smtplib.SMTP_SSL(smtp_host, smtp_port, timeout=10)
        else:
            server = smtplib.SMTP(smtp_host, smtp_port, timeout=10)
            if smtp_secure == "tls":
                server.starttls()

        if email_mode == "smtp" and smtp_user and smtp_pass:
            server.login(smtp_user, smtp_pass)

        server.sendmail(sender, [alert_email], msg.as_string())
        server.quit()
        return True
    except Exception as e:
        log.error(f"이메일 발송 실패: {e}")
        return False


async def send_email(subject: str, body: str, settings: dict = None, sender_email: str = "", receiver_email: str = "", email_mode: str = "none",
                     smtp_host: str = "", smtp_port: int = 25, smtp_user: str = "", smtp_pass: str = "", smtp_secure: str = "none",
                     alert_format: str = "plain") -> bool:
    """이메일(SMTP)로 알림을 발송합니다. 비동기 스레드 풀에서 동기 발송 처리합니다."""
    if settings is None:
        settings = await load_system_settings()
    if settings.get("email_enabled", "0") != "1":
        log.debug("이메일 알림 전역 비활성화 상태")
        return False
    return await asyncio.to_thread(
        _send_email_sync,
        subject,
        body,
        settings,
        sender_email,
        receiver_email,
        email_mode,
        smtp_host,
        smtp_port,
        smtp_user,
        smtp_pass,
        smtp_secure,
        alert_format
    )
