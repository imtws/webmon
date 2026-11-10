# =============================================
# WebMon Scheduler 컨테이너
# Python 3.12 + asyncio 기반 (PHP 없음)
# lynx / whois 는 subprocess 호출용으로만 포함
# =============================================
FROM python:3.12-slim

# lynx (모니터링 엔진) + whois (도메인 만료 체크) + curl
RUN apt-get update && apt-get install -y \
    lynx \
    whois \
    curl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Python 의존성
COPY docker/scheduler/requirements.txt /tmp/requirements.txt
RUN pip install --no-cache-dir -r /tmp/requirements.txt
RUN mkdir -p /var/lib/webmon && chown 33:33 /var/lib/webmon && chmod 700 /var/lib/webmon

# 소스 복사
COPY docker/scheduler/main.py    /app/main.py
COPY docker/scheduler/engines/   /app/engines/

# 로그 디렉토리
RUN mkdir -p /app/logs

WORKDIR /app

CMD ["python", "-u", "main.py"]
