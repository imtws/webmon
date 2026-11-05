# 🖥️ WebMon — 웹사이트 모니터링 대시보드

PHP + MariaDB + Python asyncio 기반의 웹사이트 콘텐츠 모니터링 툴입니다.

---

### 📖 [사용자 및 관리자 가이드 매뉴얼 바로가기 (MANUAL.md)](MANUAL.md)

> **상세 매뉴얼(MANUAL.md)**에서 상세 기능 설명, 대시보드 사용법, 알림 설정, 스케줄러 작동 원리 등 가이드를 자세히 확인하실 수 있습니다.

---

## 주요 기능

- **키워드 모니터링** — 설정한 URL에서 특정 키워드 존재 여부를 주기적으로 체크
- **이중 엔진** — `curl` / `lynx` 선택 지원 (JSP·전자정부 프레임워크 대응)
- **비동기 병렬 처리** — asyncio 기반으로 300개 대상도 수 초 안에 처리
- **재시도 로직** — 실패 시 설정된 횟수만큼 자동 재시도
- **SSL 만료일 추적** — 인증서 직접 조회로 자동 갱신
- **도메인 만료일 추적** — WHOIS 기반 (한국 2단계 TLD 포함)
- **텔레그램 알림** — 감지 실패를 여러 업체 한 통으로 묶어 발송 (업체별 번호 부여, 템플릿 변수 `{detections}` `{recommendations}` `{YYYY-MM-DD}` 지원)
- **SSL·도메인 만료 안내 메일** — 임박 기준일(기본 30일) 이내 항목을 WebMon 스타일 HTML 메일로 발송. 요일·시각·수신자는 설정 화면에서 지정 (이메일 전용)
- **이메일 제목 설정** — 기본 `[WebSiteMonitor] 웹사이트 모니터링 알람 발생 {YYYY-MM-DD}`, 설정 화면에서 변경 가능
- **이메일 알림 (SMTP / 직접 발송)** — 활성화한 외부 SMTP 릴레이 발송 또는 수신 도메인 메일 서버로 직접 발송 지원
- **전역 이메일 발송 주소** — 기본 발송자(From) 이메일 주소를 SMTP 릴레이 설정과 분리해 관리하며, 항목별 발송자 주소가 지정되면 항목별 주소 우선 적용
- **이메일 헤더 사이트 테마 반영 및 가독성 개선** — 메일 알림의 헤더 부분을 WebMon 고유의 스카이블루(#18aee7) 테마 및 연한 배경(#f8fafc) 조합의 인라인 스타일로 디자인하여, 일부 메일 클라이언트 다크 모드 등에서 글씨가 안 보였던 가독성 문제를 예방하고 브랜드 일관성 확보
- **민감정보 자격 증명 보안 강화** — 텔레그램 봇 토큰, Chat ID, SMTP 메일 계정, 변경된 DB 비밀번호를 DB나 Git에 저장하지 않고 비공개 Docker 런타임 볼륨에서 관리
- **DB 패스워드 동적 변경** — 웹 UI 설정 화면에서 안전하게 DB 패스워드를 변경하고, 별도의 컨테이너 재시작 없이 웹서버와 스케줄러에 즉시 반영
- **개별 발송자/수신자 지정** — 모니터링 항목별로 알림의 발송자(From) 및 수신자(To) 이메일 주소를 개별 지정 가능
- **우측 시트 기반 설정 UI** — 모니터 추가·수정, 일괄 설정, 환경 설정, 작업 이력을 우측 패널에서 관리하고, 알림은 우측 하단 토스트로 표시
- **다크모드 / 한영 전환** — TailwindCSS 기반 UI
- **그룹별 관리** — 카드/리스트 뷰, 초성 검색
- **CSV 일괄 등록** — 미리보기와 검증 후 모니터링 항목을 가져오며, 기존 URL은 덮어쓰지 않고 중복 행을 건너뜀

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
      notify.py                 ← 텔레그램/이메일 발송
      alert.py                  ← 키워드 감지 엔진 (매 1분)
      asset.py                  ← SSL/도메인 갱신 (매일 01:00)
      report.py                 ← 만료 안내 메일 (매분 틱, 설정 요일·시각에 발송)
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
```

만료 안내 메일의 발송 요일·시각은 cron이 아니라 설정 화면의 "SSL·도메인 만료 안내"에서 지정합니다 (예: 매주 월 09:00).

변경 후: `docker compose restart scheduler`

---

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
| `ALERT_CONCURRENCY` | `60` | 동시 체크 상한 |

---
