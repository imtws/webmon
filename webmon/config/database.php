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

// 자동 마이그레이션: system_settings 테이블에 smtp_mode 키 추가
$key_check = $db->query("SELECT 1 FROM `system_settings` WHERE `key_name` = 'smtp_mode'");
if ($key_check && $key_check->num_rows == 0) {
    $db->query("INSERT INTO `system_settings` (`key_name`, `key_value`) VALUES ('smtp_mode', 'direct')");
}

// 자동 마이그레이션: system_settings 테이블에 alert_format, email_template_html, tg_template_html 추가
$key_check_fmt = $db->query("SELECT 1 FROM `system_settings` WHERE `key_name` = 'alert_format'");
if ($key_check_fmt && $key_check_fmt->num_rows == 0) {
    $db->query("INSERT INTO `system_settings` (`key_name`, `key_value`) VALUES ('alert_format', 'plain')");
}

$key_check_em = $db->query("SELECT 1 FROM `system_settings` WHERE `key_name` = 'email_template_html'");
if ($key_check_em && $key_check_em->num_rows == 0) {
    $default_email_template = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: \'Apple SD Gothic Neo\', \'Malgun Gothic\', sans-serif; background-color: #f8fafc; color: #1e293b; padding: 20px; margin: 0; }
        .card { max-width: 600px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #ef4444 0%, #f97316 100%); color: #ffffff; padding: 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 800; letter-spacing: -0.5px; }
        .content { padding: 30px; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .info-table th, .info-table td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; text-align: left; }
        .info-table th { background-color: #f8fafc; color: #64748b; font-size: 12px; font-weight: 800; width: 30%; }
        .info-table td { color: #0f172a; font-size: 13px; font-weight: 600; }
        .guide-box { background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 16px; border-radius: 8px; margin-bottom: 24px; }
        .guide-box h4 { margin: 0 0 6px 0; color: #b45309; font-size: 13px; font-weight: 800; }
        .guide-box p { margin: 0; color: #78350f; font-size: 13px; }
        .footer { background-color: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #f1f5f9; }
        .btn { display: inline-block; background-color: #18aee7; color: #ffffff !important; text-decoration: none; padding: 10px 20px; font-size: 13px; font-weight: 800; border-radius: 8px; box-shadow: 0 2px 4px rgba(24,174,231,0.2); }
        .btn:hover { background-color: #159bd0; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>🚨 [WebMon] 웹사이트 장애 발생 알림</h1>
        </div>
        <div class="content">
            <table class="info-table">
                <tr>
                    <th>대상 업체</th>
                    <td>{company}</td>
                </tr>
                <tr>
                    <th>모니터링 URL</th>
                    <td><a href="{url}" target="_blank" style="color: #18aee7; text-decoration: none;">{url}</a></td>
                </tr>
                <tr>
                    <th>검출 키워드</th>
                    <td><span style="color: #ef4444; background-color: #fee2e2; padding: 2px 6px; border-radius: 4px;">{keyword}</span> (감지 불가)</td>
                </tr>
                <tr>
                    <th>발생 시간</th>
                    <td>{time}</td>
                </tr>
                <tr>
                    <th>실행 엔진</th>
                    <td>{engine}</td>
                </tr>
                <tr>
                    <th>응답 속도</th>
                    <td>{response_time}s</td>
                </tr>
            </table>
            
            <div class="guide-box">
                <h4>💡 권장 처리 가이드</h4>
                <p>{comment}</p>
            </div>
        </div>
        <div class="footer">
            <a href="{web_url}" class="btn" target="_blank">대시보드 바로가기</a>
        </div>
    </div>
</body>
</html>';
    $stmt = $db->prepare("INSERT INTO `system_settings` (`key_name`, `key_value`) VALUES ('email_template_html', ?)");
    $stmt->bind_param("s", $default_email_template);
    $stmt->execute();
    $stmt->close();
}

$key_check_tg = $db->query("SELECT 1 FROM `system_settings` WHERE `key_name` = 'tg_template_html'");
if ($key_check_tg && $key_check_tg->num_rows == 0) {
    $default_tg_template = '🚨 <b>[WebMon] 웹사이트 장애 발생 알림</b>

📌 <b>대상 업체</b>: {company}
🔗 <b>대상 URL</b>: {url}
❌ <b>감지 실패 키워드</b>: <code>{keyword}</code>
⏱️ <b>발생 시간</b>: {time}
⚙️ <b>실행 엔진</b>: {engine} ({response_time}s)

💡 <b>권장 처리 내용</b>:
{comment}

🖥️ <a href="{web_url}">대시보드 바로가기</a>';
    $stmt = $db->prepare("INSERT INTO `system_settings` (`key_name`, `key_value`) VALUES ('tg_template_html', ?)");
    $stmt->bind_param("s", $default_tg_template);
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
?>