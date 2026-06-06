# 🖥️ WebMon — 웹사이트 모니터링 대시보드

PHP + MariaDB + Python asyncio 기반의 웹사이트 콘텐츠 모니터링 툴입니다.

---

## 주요 기능

- **키워드 모니터링** — 설정한 URL에서 특정 키워드 존재 여부를 주기적으로 체크
- **이중 엔진** — `curl` / `lynx` 선택 지원 (JSP·전자정부 프레임워크 대응)
- **비동기 병렬 처리** — asyncio 기반으로 300개 대상도 수 초 안에 처리
- **재시도 로직** — 실패 시 설정된 횟수만큼 자동 재시도
- **SSL 만료일 추적** — 인증서 직접 조회로 자동 갱신
- **도메인 만료일 추적** — WHOIS 기반 (한국 2단계 TLD 포함)
- **텔레그램 알림** — 감지 실패 즉시 + 주간 만료 예정 리포트
- **다크모드 / 한영 전환** — TailwindCSS 기반 UI
- **그룹별 관리** — 카드/리스트 뷰, 초성 검색

---

## 기술 스택

| 구성 요소 | 기술 |
|-----------|------|
| UI | PHP 8.2 + Apache + TailwindCSS |
| 모니터링 엔진 | Python 3.12 + asyncio + aiohttp |
| 스케줄러 | APScheduler 3.10 |
| DB | MariaDB 10.11 + aiomysql |
| 배포 | Docker Compose |

---

## 디렉토리 구조

```
webmon/                         ← PHP UI 소스
docker/
  php/
    Dockerfile
    php.ini
    apache.conf
  scheduler/
    Dockerfile                  ← Python 전용 (PHP 없음)
    main.py                     ← APScheduler 진입점
    requirements.txt
    engines/
      __init__.py
      db.py                     ← aiomysql 커넥션 풀
      notify.py                 ← 텔레그램 발송
      alert.py                  ← 키워드 감지 엔진 (매 1분)
      asset.py                  ← SSL/도메인 갱신 (매일 01:00)
      report.py                 ← 주간 리포트 (매주 월 09:00)
  mysql/
    init/
      01_schema.sql

docker-compose.yml
.env.example
```

---

## 빠른 시작

```bash
# 1. 저장소 클론
git clone https://github.com/yourname/webmon.git
cd webmon

# 2. 환경변수 설정
cp .env.example .env
# .env 에서 DB_PASS, DB_ROOT_PASS, TG_TOKEN, TG_CHAT_ID 입력

# 3. 실행
docker compose up -d --build

# 접속
# http://localhost:8080
```

---

## 로그 확인

```bash
# 엔진 실행 결과 실시간 확인
docker compose logs -f scheduler

# 웹서버
docker compose logs -f web
```

---

## 스케줄 조정

`.env` 의 cron 표현식으로 제어합니다:

```env
SCHEDULE_ALERT=* * * * *     # 매 1분
SCHEDULE_ASSET=0 1 * * *     # 매일 새벽 1시
SCHEDULE_REPORT=0 9 * * 1    # 매주 월요일 9시
```

변경 후: `docker compose restart scheduler`

---

## 개발용 (phpMyAdmin 포함)

```bash
docker compose --profile dev up -d --build
# phpMyAdmin: http://localhost:8081
```

---

## 환경변수 전체 목록

| 변수 | 기본값 | 설명 |
|------|--------|------|
| `DB_PASS` | 필수 | DB 비밀번호 |
| `DB_ROOT_PASS` | 필수 | DB root 비밀번호 |
| `TG_TOKEN` | 필수 | 텔레그램 봇 토큰 |
| `TG_CHAT_ID` | 필수 | 텔레그램 chat ID |
| `ALERT_EMAIL` | — | 알람 수신 이메일 (미사용 시 생략) |
| `WEBMON_URL` | `http://localhost:8080` | 알림 메시지에 포함될 URL |
| `PHP_TIMEZONE` | `Asia/Seoul` | 타임존 |
| `SCHEDULE_ALERT` | `* * * * *` | 키워드 감지 스케줄 |
| `SCHEDULE_ASSET` | `0 1 * * *` | SSL/도메인 갱신 스케줄 |
| `SCHEDULE_REPORT` | `0 9 * * 1` | 주간 리포트 스케줄 |
| `ALERT_CONCURRENCY` | `60` | 동시 체크 상한 |

---
