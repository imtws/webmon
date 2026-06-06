<?php
// =============================================
// DB 연결 - 환경변수 기반 (Docker/보안 대응)
// =============================================

$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'sitemonitor';
$db_pass = getenv('DB_PASS') ?: '';
$db_name = getenv('DB_NAME') ?: 'sitemonitor';
$db_port = (int)(getenv('DB_PORT') ?: 3306);

// Check for custom password override
$custom_pass_file = __DIR__ . '/db_pass_custom.php';
if (file_exists($custom_pass_file)) {
    $custom_pass = include $custom_pass_file;
    if (!empty($custom_pass)) {
        $db_pass = $custom_pass;
    }
}

$db = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

if ($db->connect_error) {
    // 프로덕션에서는 상세 에러 노출 금지
    $debug = getenv('APP_DEBUG') === 'true';
    if ($debug) {
        die("DB 연결 실패: " . $db->connect_error);
    } else {
        die("서비스 연결에 실패했습니다. 관리자에게 문의하세요.");
    }
}

$db->set_charset('utf8mb4');

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

// 자동 마이그레이션: system_settings 테이블에 smtp_mode 키 추가
$key_check = $db->query("SELECT 1 FROM `system_settings` WHERE `key_name` = 'smtp_mode'");
if ($key_check && $key_check->num_rows == 0) {
    $db->query("INSERT INTO `system_settings` (`key_name`, `key_value`) VALUES ('smtp_mode', 'direct')");
}

// 시스템 설정 로드
$settings = [];
$res = $db->query("SELECT key_name, key_value FROM system_settings");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $settings[$row['key_name']] = $row['key_value'];
    }
}
?>