<?php
// =============================================
// DB 연결 - 환경변수 기반 (Docker/보안 대응)
// =============================================

$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'sitemonitor';
$db_pass = getenv('DB_PASS') ?: '';
$db_name = getenv('DB_NAME') ?: 'sitemonitor';
$db_port = (int)(getenv('DB_PORT') ?: 3306);

// Prefer the private runtime volume; keep the old file as a migration fallback.
$runtime_pass_file = '/var/lib/webmon/db_pass';
if (is_readable($runtime_pass_file)) {
    $runtime_pass = file_get_contents($runtime_pass_file);
    if ($runtime_pass !== false && $runtime_pass !== '') {
        $db_pass = $runtime_pass;
    }
} else {
    $custom_pass_file = __DIR__ . '/db_pass_custom.php';
    if (file_exists($custom_pass_file)) {
        $custom_pass = include $custom_pass_file;
        if (!empty($custom_pass)) {
            $db_pass = $custom_pass;
        }
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
    'last_whois_check_at' => "DATETIME DEFAULT NULL COMMENT '마지막 실시간 WHOIS 확인 시각'"
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

$key_check_em = $db->query("SELECT key_value FROM `system_settings` WHERE `key_name` = 'email_template_html'");
$needs_update_em = false;
if ($key_check_em) {
    if ($key_check_em->num_rows == 0) {
        $needs_update_em = true;
    } else {
        $row = $key_check_em->fetch_assoc();
        if (strpos($row['key_value'], 'class="card"') !== false && strpos($row['key_value'], 'style="padding: 12px 16px;') === false) {
            $needs_update_em = true;
        }
    }
}

if ($needs_update_em) {
    $default_email_template = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: \'Apple SD Gothic Neo\', \'Malgun Gothic\', sans-serif; background-color: #f8fafc; color: #1e293b; padding: 20px; margin: 0; -webkit-text-size-adjust: none; -ms-text-size-adjust: none;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
        <div style="background-color: #f8fafc; color: #18aee7; padding: 24px; text-align: center; border-bottom: 1px solid #e2e8f0;">
            <h1 style="margin: 0; font-size: 20px; font-weight: 800; color: #18aee7;">🚨 [WebMon] 웹사이트 장애 발생 알림</h1>
        </div>
        <div style="padding: 30px; background-color: #ffffff;">
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px; background-color: #ffffff;">
                <tr style="background-color: #ffffff;">
                    <th style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; text-align: left; background-color: #f8fafc; color: #64748b; font-size: 12px; font-weight: 800; width: 30%;">대상 업체</th>
                    <td style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; text-align: left; color: #0f172a; font-size: 13px; font-weight: 600; background-color: #ffffff;">{company}</td>
                </tr>
                <tr style="background-color: #ffffff;">
                    <th style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; text-align: left; background-color: #f8fafc; color: #64748b; font-size: 12px; font-weight: 800;">모니터링 URL</th>
                    <td style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; text-align: left; color: #18aee7; font-size: 13px; font-weight: 600; background-color: #ffffff;"><a href="{url}" target="_blank" style="color: #18aee7; text-decoration: none;">{url}</a></td>
                </tr>
                <tr style="background-color: #ffffff;">
                    <th style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; text-align: left; background-color: #f8fafc; color: #64748b; font-size: 12px; font-weight: 800;">검출 키워드</th>
                    <td style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; text-align: left; color: #0f172a; font-size: 13px; font-weight: 600; background-color: #ffffff;"><span style="color: #ef4444; background-color: #fee2e2; padding: 2px 6px; border-radius: 4px; font-weight: 800;">{keyword}</span> (감지 불가)</td>
                </tr>
                <tr style="background-color: #ffffff;">
                    <th style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; text-align: left; background-color: #f8fafc; color: #64748b; font-size: 12px; font-weight: 800;">발생 시간</th>
                    <td style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; text-align: left; color: #0f172a; font-size: 13px; font-weight: 600; background-color: #ffffff;">{time}</td>
                </tr>
                <tr style="background-color: #ffffff;">
                    <th style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; text-align: left; background-color: #f8fafc; color: #64748b; font-size: 12px; font-weight: 800;">실행 엔진</th>
                    <td style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; text-align: left; color: #0f172a; font-size: 13px; font-weight: 600; background-color: #ffffff;">{engine}</td>
                </tr>
                <tr style="background-color: #ffffff;">
                    <th style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; text-align: left; background-color: #f8fafc; color: #64748b; font-size: 12px; font-weight: 800;">응답 속도</th>
                    <td style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; text-align: left; color: #0f172a; font-size: 13px; font-weight: 600; background-color: #ffffff;">{response_time}s</td>
                </tr>
            </table>
            
            <div style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
                <h4 style="margin: 0 0 6px 0; color: #b45309; font-size: 13px; font-weight: 800;">💡 권장 처리 가이드</h4>
                <p style="margin: 0; color: #78350f; font-size: 13px; line-height: 1.5;">{comment}</p>
            </div>
        </div>
        <div style="background-color: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #f1f5f9;">
            <a href="{web_url}" style="display: inline-block; background-color: #18aee7; color: #ffffff !important; text-decoration: none; padding: 10px 20px; font-size: 13px; font-weight: 800; border-radius: 8px; box-shadow: 0 2px 4px rgba(24,174,231,0.2);" target="_blank">대시보드 바로가기</a>
        </div>
    </div>
</body>
</html>';
    $stmt = $db->prepare("INSERT INTO `system_settings` (`key_name`, `key_value`) VALUES ('email_template_html', ?) ON DUPLICATE KEY UPDATE `key_value` = VALUES(`key_value`)");
    $stmt->bind_param("s", $default_email_template);
    $stmt->execute();
    $stmt->close();
}

$key_check_tg = $db->query("SELECT 1 FROM `system_settings` WHERE `key_name` = 'tg_template_plain'");
$legacy_default_tg_template = '🚨 [WebMon] 웹사이트 장애 발생 알림

📌 대상 업체: {company}
🔗 대상 URL: {url}
❌ 감지 실패 키워드: {keyword}
⏱️ 발생 시간: {time}
⚙️ 실행 엔진: {engine} ({response_time}s)

💡 권장 처리 내용:
{comment}

🖥️ 대시보드: {web_url}';
$default_tg_template = '[WebSiteMonitor 알람발생]

시간 : {time}

-------------------------- 감지 내용 --------------------------
{detections}
-------------------------- 권 장 처 리 -------------------------
{recommendations}
--------------------------------------------------------------

웹사이트 모니터링 URL : {web_url}';
if ($key_check_tg && $key_check_tg->num_rows > 0) {
    $stmt = $db->prepare("UPDATE `system_settings` SET `key_value` = ? WHERE `key_name` = 'tg_template_plain' AND `key_value` = ?");
    $stmt->bind_param("ss", $default_tg_template, $legacy_default_tg_template);
    $stmt->execute();
    $stmt->close();
}
if ($key_check_tg && $key_check_tg->num_rows == 0) {
    $stmt = $db->prepare("INSERT INTO `system_settings` (`key_name`, `key_value`) VALUES ('tg_template_plain', ?)");
    $stmt->bind_param("s", $default_tg_template);
    $stmt->execute();
    $stmt->close();
}

$key_check_pl = $db->query("SELECT 1 FROM `system_settings` WHERE `key_name` = 'email_template_plain'");
if ($key_check_pl && $key_check_pl->num_rows == 0) {
    $default_plain_template = "[WebSiteMonitor 알람발생]

시간 : {time}

-------------------------- 감지 내용 --------------------------
{detections}
-------------------------- 권 장 처 리 -------------------------
{recommendations}
--------------------------------------------------------------

웹사이트 모니터링 : {web_url}";
    $stmt = $db->prepare("INSERT INTO `system_settings` (`key_name`, `key_value`) VALUES ('email_template_plain', ?)");
    $stmt->bind_param("s", $default_plain_template);
    $stmt->execute();
    $stmt->close();
}


// 시스템 설정 로드
$settings = [];
$res = $db->query("SELECT key_name, key_value FROM system_settings");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $settings[$row['key_name']] = $row['key_value'];
    }
}

// .env 설정 로딩 및 데이터 병합 (민감정보 전용)
require_once __DIR__ . '/../classes/Utils.php';
$env_settings = Utils::readEnv();
foreach ($env_settings as $k => $v) {
    if ($k === 'TG_TOKEN') $settings['tg_token'] = $v;
    elseif ($k === 'TG_CHAT_ID') $settings['tg_chat_id'] = $v;
    elseif ($k === 'SMTP_HOST') $settings['smtp_host'] = $v;
    elseif ($k === 'SMTP_PORT') $settings['smtp_port'] = $v;
    elseif ($k === 'SMTP_USER') $settings['smtp_user'] = $v;
    elseif ($k === 'SMTP_PASS') $settings['smtp_pass'] = $v;
    elseif ($k === 'SMTP_SECURE') $settings['smtp_secure'] = $v;
    elseif ($k === 'WEBMON_URL') $settings['webmon_url'] = $v;
}
?>