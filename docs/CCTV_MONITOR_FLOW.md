# CCTV 모니터링 & 자가 치유 시스템 (cctv-monitor) 백엔드 동작 흐름

기능경기대회 경기장 DID(Digital Information Display) 솔루션에 송출되는 실시간 카메라 스트림을 안정적으로 유지하고 관제하기 위한 모니터링 및 자동 복구 시스템의 아키텍처와 백엔드 파이프라인 문서입니다.

---

## 1. 전체 시스템 아키텍처

```mermaid
flowchart TD
    subgraph Edge ["현장 Edge 캠 서버 (각 경기장)"]
        Cam["현장 IP 카메라 (RTSP)"] -->|RTSP 수신| FFMPEG["FFmpeg (cctv-relay.service)"]
        FFMPEG -->|HLS 세그먼트 생성| Nginx["Nginx Web Server (/hls/webcam.m3u8)"]
        
        CronSH["로컬 Watchdog (check_ffmpeg.sh, 1분 cron)"]
        CronSH -->|1. mtime 지연 검사\n2. HLS Stall duration 파싱| Nginx
        CronSH -->|이상 감지 시 자동 재기동| FFMPEG
        CronSH -->|자가 치유/상태 기록| StatusFile["상태 파일 (*_cctv-relay_status)"]
        
        NRPEPlugin["NRPE 플러그인 (check_cctv_status.sh)"] -->|상태 및 Heartbeat 검증| StatusFile
    end

    subgraph Central ["중앙 관제 서버"]
        DigestCron["통합 알람 수집기 (cctv_digest.sh, 5분 cron)"]
        DigestCron -->|병렬 NRPE 질의 (PARALLEL=20)| NRPEPlugin
        DigestCron -->|Nagios 호스트 동적 디렉터리 스캔| NagiosCfg["/etc/nagios/objects/Monitor/"]
        
        DigestCron -->|예외/스케줄 필터링| ConfigData["data/cctv_config.json\ndata/alert_config.json"]
        DigestCron -->|장애 전파| Mail["담당자 이메일 발송"]
        DigestCron -->|실시간 상태 동기화| LiveJSON["data/live_status.json"]
    end

    subgraph WebAdmin ["웹 관제 대시보드 (PHP MVC SPA)"]
        AdminUser["관제 관리자"] -->|브라우저 접속| WebUI["Web UI (Card / List 뷰)"]
        WebUI -->|HLS 실시간 스트리밍| Nginx
        WebUI -->|API 호출 (api.php)| Controller["CctvController.php"]
        Controller --> Model["CctvModel.php (Atomic File CRUD)"]
        
        Upload["엑셀 업로드 (.xlsx)"] --> Controller
        Controller --> ExcelPy["scripts/parse_excel.py"]
        ExcelPy -->|카메라 목록 동기화| ConfigData
    end
```

---

## 2. 단계별 상세 동작 흐름

### Step 1. Edge 캠 서버: FFmpeg HLS 변환 및 지능형 자가 치유 (Self-Healing)
* **FFmpeg 스트리밍**: 현장 카메라의 RTSP 스트림을 Nginx HLS 포맷(`.m3u8` 및 `.ts` 세그먼트)으로 실시간 트랜스코딩.
* **지능형 Watchdog (`check_ffmpeg.sh`)**:
  - **1차 검증 (File mtime)**: `webcam.m3u8`의 수정 시간이 30초 이상 갱신되지 않으면 스트림 단절로 판정.
  - **2차 검증 (HLS 0초 멈춤 / Stall 감지)**: 파일 수정 시간(mtime)은 갱신되지만 실제 재생 시간이 멈추는 특이 장애를 감지하기 위해 m3u8 내부 메타데이터를 직접 파싱:
    $$\text{TARGETDURATION} < 1 \quad \text{혹은} \quad \text{EXTINF 평균} < 0.5\text{초}$$
    위 조건에 부합할 경우 `cctv-relay.service`를 자동으로 재기동(`systemctl restart`)하여 무중단 복구.
  - **자가 치유 이력 기록**: 자동 복구 시각(`SELF_HEAL_AT`)과 원인(`SELF_HEAL_REASON`)을 로컬 상태 파일에 기록.

---

### Step 2. 상태 수집 및 2중 안전장치 (`check_cctv_status.sh`)
* **NRPE 에이전트 연동**: 중앙 서버의 질의에 로컬 상태 파일 내용을 반환.
* **로컬 크론 데몬 생존 감증 (Heartbeat Check)**:
  - 상태 파일 자체의 수정 시간(`FILE_AGE`)을 체크.
  - 만약 로컬 크론이나 감시 스크립트가 죽어 상태 파일이 180초 이상 방치된 경우, 파일 내부 상태(OK)와 무관하게 즉시 `CRITICAL` 상태를 반환하여 관제 공백 방지.

---

### Step 3. 중앙 관제 서버: 대규모 병렬 수집 및 동적 라우팅 (`cctv_digest.sh`)
* **동적 호스트 자동 인식**:
  - `/etc/nagios/objects/Monitor/` 하위 디렉터리를 런타임에 자동 탐색하여 신규 계정/호스트가 추가되어도 설정 수정 없이 카메라 번호와 자동 매핑.
* **병렬 NRPE 파이프라인**:
  - `PARALLEL=20` 프로세스 풀을 생성하여 수십~수백 대의 캠 상태를 수 초 내에 고속 병렬 수집.
* **스마트 예외 처리 및 알람 스케줄링**:
  - 전역 알람 시간대, 개별 카메라별 알람 스케줄(`alert_start` ~ `alert_end`), 임시 억제(`suppress_until`), 엑셀 미등록 호스트 등을 필터링하여 불필요한 알람 피로도 차단.
  - 이전 주기 상태(`cctv_digest_prev_problems.txt`)와 비교하여 신규 장애(`PROBLEM`) 및 복구(`RECOVERY`) 메일만 선택 발송.

---

### Step 4. 웹 관제 대시보드 및 운영 자동화 (PHP MVC)
* **대량 도메인/카메라 동기화**:
  - 대회 운영진이 경기장 배정표(`.xlsx`)를 업로드하면 Python(`parse_excel.py`)을 통해 카메라 번호, 종목, 경기장, DDNS 정보를 추출하고 기존 알림 설정을 보존하면서 `cctv_config.json`을 원자적(Atomic Write)으로 갱신.
* **실시간 모니터링 & HLS 재생**:
  - `data/live_status.json`과 연동하여 세그먼트 갱신 지연 상태를 실시간 배지로 노출.
  - 각 카메라의 HLS 스트림을 모달 플레이어 및 리스트 인라인으로 지연 없이 시청.
* **현장 대응 SMS 템플릿 자동 생성**:
  - 장애 발생 캠 목록을 자동 취합하여 현장 담당자에게 발송할 SMS 양식을 원클릭 생성.
