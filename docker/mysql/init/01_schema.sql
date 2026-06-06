-- =============================================
-- WebMon 초기 DB 스키마
-- Docker 최초 실행 시 자동으로 실행됩니다
-- =============================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

USE `sitemonitor`;

-- 모니터링 설정 테이블
CREATE TABLE IF NOT EXISTS `monitor_conf` (
    `no`            INT(11)         NOT NULL AUTO_INCREMENT,
    `company`       VARCHAR(200)    NOT NULL DEFAULT '' COMMENT '회사명',
    `group_name`    VARCHAR(100)    NOT NULL DEFAULT '기본 그룹' COMMENT '그룹명',
    `url`           VARCHAR(500)    NOT NULL DEFAULT '' COMMENT '모니터링 URL',
    `contents`      VARCHAR(500)    NOT NULL DEFAULT '' COMMENT '확인 키워드',
    `check`         VARCHAR(20)     NOT NULL DEFAULT 'alarm' COMMENT 'alarm|no alarm|disable',
    `ssl_mon`       TINYINT(1)      NOT NULL DEFAULT 1 COMMENT 'SSL 만료 모니터링 여부',
    `dom_mon`       TINYINT(1)      NOT NULL DEFAULT 1 COMMENT '도메인 만료 모니터링 여부',
    `ssl_expiry`    DATE            DEFAULT NULL COMMENT 'SSL 만료일',
    `dom_expiry`    DATE            DEFAULT NULL COMMENT '도메인 만료일',
    `comment`       VARCHAR(500)    NOT NULL DEFAULT '' COMMENT '권장 처리 내용',
    `sender_email`  VARCHAR(255)    NOT NULL DEFAULT '' COMMENT '발송자 이메일',
    `receiver_email` VARCHAR(255)    NOT NULL DEFAULT '' COMMENT '수신자 이메일',
    `mod_date`      DATETIME        DEFAULT NULL COMMENT '마지막 수정/실행 시각',
    `value`         TINYINT(1)      NOT NULL DEFAULT 1 COMMENT '마지막 체크 결과 (1=성공, 0=실패)',
    `response_time` FLOAT           NOT NULL DEFAULT 0.0 COMMENT '응답시간(초)',
    `retry_count`   INT(3)          NOT NULL DEFAULT 1 COMMENT '재시도 횟수',
    `timeout_sec`   INT(3)          NOT NULL DEFAULT 2 COMMENT '타임아웃(초)',
    `engine_type`   VARCHAR(10)     NOT NULL DEFAULT 'lynx' COMMENT 'lynx|curl',
    `cross_check`   TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '크로스 체크 여부',
    `sched_enabled` TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '스케줄링 활성 여부',
    `sched_start_time` VARCHAR(5)   NOT NULL DEFAULT '09:00' COMMENT '스케줄 시작 시간',
    `sched_end_time` VARCHAR(5)     NOT NULL DEFAULT '18:00' COMMENT '스케줄 종료 시간',
    `sched_mode`    VARCHAR(10)     NOT NULL DEFAULT 'active' COMMENT '스케줄 모드 (active|mute)',
    PRIMARY KEY (`no`),
    INDEX `idx_group`  (`group_name`),
    INDEX `idx_check`  (`check`),
    INDEX `idx_value`  (`value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 엔진 실행 로그 테이블 (선택사항, 로그 뷰어 기능용)
CREATE TABLE IF NOT EXISTS `monitor_log` (
    `id`            INT(11)         NOT NULL AUTO_INCREMENT,
    `monitor_conf_no` INT(11)       DEFAULT NULL COMMENT '대상설정 외래키',
    `log_time`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `req_url`       VARCHAR(500)    NOT NULL DEFAULT '',
    `response_time` FLOAT           NOT NULL DEFAULT 0.0,
    `cmd`           TEXT            DEFAULT NULL COMMENT '실행 커맨드',
    `result`        TINYINT(1)      NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    INDEX `idx_log_time` (`log_time`),
    INDEX `idx_conf_no` (`monitor_conf_no`),
    INDEX `idx_log_time_no` (`log_time`, `monitor_conf_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 샘플 데이터 (선택사항 - 삭제해도 무방)
INSERT INTO `monitor_conf` (`company`, `group_name`, `url`, `contents`, `check`, `ssl_mon`, `dom_mon`, `comment`, `mod_date`, `engine_type`) VALUES
('Google', '샘플 그룹', 'https://www.google.com', 'Google', 'alarm', 1, 0, '', NOW(), 'curl'),
('Naver', '샘플 그룹', 'https://www.naver.com', '네이버', 'alarm', 1, 1, '', NOW(), 'curl');

-- 전역 시스템 설정 테이블 생성
CREATE TABLE IF NOT EXISTS `system_settings` (
    `key_name`  VARCHAR(100) NOT NULL,
    `key_value` TEXT DEFAULT NULL,
    PRIMARY KEY (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 초기 기본값 삽입
INSERT INTO `system_settings` (`key_name`, `key_value`) VALUES
('tg_enabled', '0'),
('tg_token', ''),
('tg_chat_id', ''),
('email_enabled', '0'),
('smtp_mode', 'direct'),
('smtp_host', 'mail'),
('smtp_port', '25'),
('smtp_user', ''),
('smtp_pass', ''),
('smtp_secure', 'none'),
('alert_email', ''),
('retention_days', '30');

