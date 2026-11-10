<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $db_config_file = file_exists('/var/www/html/config/database.php')
        ? '/var/www/html/config/database.php'
        : __DIR__ . '/../../webmon/config/database.php';
    require $db_config_file;
    $lockName = 'webmon_schema_' . substr(hash('sha256', $db_name), 0, 40);
    $lock = $db->prepare('SELECT GET_LOCK(?, 60)');
    $lock->bind_param('s', $lockName);
    $lock->execute();
    if ((int)$lock->get_result()->fetch_row()[0] !== 1) {
        throw new RuntimeException('Could not acquire schema migration lock.');
    }

    try {
        $db->query("CREATE TABLE IF NOT EXISTS `schema_migrations` (
            `version` INT NOT NULL PRIMARY KEY,
            `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $applied = $db->query('SELECT 1 FROM schema_migrations WHERE version = 1');
        if ($applied->num_rows === 0) {
            // 자동 마이그레이션: job_history 테이블 생성
            $table_check = $db->query("SHOW TABLES LIKE 'job_history'");
            if ($table_check && $table_check->num_rows == 0) {
                $db->query("CREATE TABLE IF NOT EXISTS `job_history` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `job_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `action_type` VARCHAR(50) NOT NULL,
                    `target_count` INT(11) NOT NULL DEFAULT 0,
                    `changed_fields` TEXT DEFAULT NULL,
                    `snapshot` LONGTEXT NOT NULL,
                    `is_rolled_back` TINYINT(1) NOT NULL DEFAULT 0,
                    `rolled_back_time` DATETIME DEFAULT NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            }

            // 자동 마이그레이션: monitor_conf 테이블에 sender_email 컬럼 추가
            $col_check = $db->query("SHOW COLUMNS FROM `monitor_conf` LIKE 'sender_email'");
            if ($col_check && $col_check->num_rows == 0) {
                $db->query("ALTER TABLE `monitor_conf` ADD COLUMN `sender_email` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '발송자 이메일' AFTER `comment`");
            }

            // 자동 마이그레이션: monitor_conf 테이블에 receiver_email 컬럼 추가
            $col_check2 = $db->query("SHOW COLUMNS FROM `monitor_conf` LIKE 'receiver_email'");
            if ($col_check2 && $col_check2->num_rows == 0) {
                $db->query("ALTER TABLE `monitor_conf` ADD COLUMN `receiver_email` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '수신자 이메일' AFTER `sender_email`");
            }

            // 자동 마이그레이션: monitor_conf 테이블에 email_mode 컬럼 추가
            $col_check3 = $db->query("SHOW COLUMNS FROM `monitor_conf` LIKE 'email_mode'");
            if ($col_check3 && $col_check3->num_rows == 0) {
                $db->query("ALTER TABLE `monitor_conf` ADD COLUMN `email_mode` VARCHAR(20) NOT NULL DEFAULT 'none' COMMENT '이메일 발송 방식' AFTER `receiver_email`");
            }

            // 자동 마이그레이션: monitor_conf 테이블에 mute_until 컬럼 추가
            $col_check4 = $db->query("SHOW COLUMNS FROM `monitor_conf` LIKE 'mute_until'");
            if ($col_check4 && $col_check4->num_rows == 0) {
                $db->query("ALTER TABLE `monitor_conf` ADD COLUMN `mute_until` DATETIME NULL COMMENT '임시 무음 기한' AFTER `email_mode`");
            }

            // 자동 마이그레이션: monitor_conf 테이블에 개별 SMTP 컬럼들 추가
            $smtp_cols = [
                'smtp_host' => "VARCHAR(255) NOT NULL DEFAULT '' COMMENT '개별 SMTP 호스트'",
                'smtp_port' => "INT NOT NULL DEFAULT 25 COMMENT '개별 SMTP 포트'",
                'smtp_user' => "VARCHAR(255) NOT NULL DEFAULT '' COMMENT '개별 SMTP 계정'",
                'smtp_pass' => "VARCHAR(255) NOT NULL DEFAULT '' COMMENT '개별 SMTP 비번'",
                'smtp_secure' => "VARCHAR(50) NOT NULL DEFAULT 'none' COMMENT '개별 SMTP 보안'"
            ];
            foreach ($smtp_cols as $col => $def) {
                $col_chk = $db->query("SHOW COLUMNS FROM `monitor_conf` LIKE '$col'");
                if ($col_chk && $col_chk->num_rows == 0) {
                    $db->query("ALTER TABLE `monitor_conf` ADD COLUMN `$col` $def AFTER `mute_until`");
                }
            }

            // 자동 마이그레이션: 설정 변경/모니터링/자산 확인 시각 분리
            $monitor_timestamps = [
                'updated_at' => "DATETIME DEFAULT NULL COMMENT '설정 수정 시각'",
                'last_checked_at' => "DATETIME DEFAULT NULL COMMENT '마지막 모니터링 실행 시각'",
                'last_asset_check_at' => "DATETIME DEFAULT NULL COMMENT '마지막 SSL/도메인 정보 확인 시각'",
                'last_whois_check_at' => "DATETIME DEFAULT NULL COMMENT '마지막 실시간 WHOIS 확인 시각'",
                'fail_since' => "DATETIME DEFAULT NULL COMMENT '현재 장애 시작 시각'",
                'http_status' => "SMALLINT DEFAULT NULL COMMENT '마지막 체크의 HTTP 상태코드'"
            ];
            foreach ($monitor_timestamps as $col => $def) {
                $db->query("ALTER TABLE `monitor_conf` ADD COLUMN IF NOT EXISTS `$col` $def");
            }
            $db->query("UPDATE monitor_conf SET updated_at = mod_date WHERE updated_at IS NULL AND mod_date IS NOT NULL");
            $db->query("UPDATE monitor_conf SET last_asset_check_at = mod_date WHERE last_asset_check_at IS NULL AND mod_date IS NOT NULL");
            $db->query("UPDATE monitor_conf c
                SET last_checked_at = (
                    SELECT MAX(l.log_time) FROM monitor_log l WHERE l.monitor_conf_no = c.no
                )
                WHERE c.last_checked_at IS NULL
                  AND EXISTS (SELECT 1 FROM monitor_log l WHERE l.monitor_conf_no = c.no)");

            // 자동 마이그레이션: system_settings 테이블에 smtp_mode 키 추가
            $key_check = $db->query("SELECT 1 FROM `system_settings` WHERE `key_name` = 'smtp_mode'");
            if ($key_check && $key_check->num_rows == 0) {
                $db->query("INSERT INTO `system_settings` (`key_name`, `key_value`) VALUES ('smtp_mode', 'direct')");
            }

            // 자동 마이그레이션: system_settings 테이블에 알림 형식과 템플릿 추가
            $key_check_fmt = $db->query("SELECT 1 FROM `system_settings` WHERE `key_name` = 'alert_format'");
            if ($key_check_fmt && $key_check_fmt->num_rows == 0) {
                $db->query("INSERT INTO `system_settings` (`key_name`, `key_value`) VALUES ('alert_format', 'plain')");
            }


            $db->query('INSERT INTO schema_migrations (version) VALUES (1)');
            fwrite(STDOUT, "WebMon schema migration 1 applied.\n");
        } else {
            fwrite(STDOUT, "WebMon database schema is up to date (version 1).\n");
        }

        $applied2 = $db->query('SELECT 1 FROM schema_migrations WHERE version = 2');
        if ($applied2->num_rows === 0) {
            $db->query("ALTER TABLE `monitor_conf` ADD COLUMN IF NOT EXISTS `expiry_receiver_email` VARCHAR(500) DEFAULT NULL COMMENT '인증서/도메인 만료 수신 이메일'");
            $db->query("ALTER TABLE `monitor_conf` ADD COLUMN IF NOT EXISTS `expiry_cc_email` VARCHAR(500) DEFAULT NULL COMMENT '인증서/도메인 만료 참조 이메일'");
            $db->query('INSERT INTO schema_migrations (version) VALUES (2)');
            fwrite(STDOUT, "WebMon schema migration 2 applied (expiry_receiver_email, expiry_cc_email).\n");
        }
    } finally {
        $release = $db->prepare('SELECT RELEASE_LOCK(?)');
        $release->bind_param('s', $lockName);
        $release->execute();
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'WebMon schema migration failed: ' . $error->getMessage() . "\n");
    exit(1);
}
