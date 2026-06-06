<?php
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. DB 비밀번호 변경 처리
    $db_pass_old = $_POST['db_pass_old'] ?? '';
    $db_pass_new = $_POST['db_pass_new'] ?? '';

    if (!empty($db_pass_old) && !empty($db_pass_new)) {
        $db_host = getenv('DB_HOST') ?: 'localhost';
        $db_user = getenv('DB_USER') ?: 'sitemonitor';
        $db_name = getenv('DB_NAME') ?: 'sitemonitor';
        $db_port = (int)(getenv('DB_PORT') ?: 3306);

        // 이전 비밀번호가 올바른지 검증 시도
        $test_conn = @new mysqli($db_host, $db_user, $db_pass_old, $db_name, $db_port);
        if ($test_conn->connect_error) {
            header("Location: website_status.php?settings_error=db_pass_incorrect");
            exit;
        }

        // 비밀번호 변경 쿼리 실행
        $escaped_pass_new = str_replace("'", "''", $db_pass_new);
        $sql_alter = "ALTER USER CURRENT_USER() IDENTIFIED BY '$escaped_pass_new'";
        if ($test_conn->query($sql_alter)) {
            // 변경 성공 시 config/db_pass_custom.php 파일에 기록
            $config_content = "<?php\n// DB_PASSWORD_OVERRIDE: " . str_replace(["\r", "\n"], "", $db_pass_new) . "\nreturn " . var_export($db_pass_new, true) . ";\n";
            file_put_contents(__DIR__ . '/config/db_pass_custom.php', $config_content);
            
            // 현재 활성 DB 커넥션도 새 비밀번호로 갱신
            $db->close();
            $db = new mysqli($db_host, $db_user, $db_pass_new, $db_name, $db_port);
            $db->set_charset('utf8mb4');
        } else {
            header("Location: website_status.php?settings_error=db_alter_failed");
            $test_conn->close();
            exit;
        }
        $test_conn->close();
    }

    $tg_enabled = isset($_POST['tg_enabled']) ? '1' : '0';
    $tg_token = trim($_POST['tg_token'] ?? '');
    $tg_chat_id = trim($_POST['tg_chat_id'] ?? '');

    $email_enabled = isset($_POST['email_enabled']) ? '1' : '0';
    $smtp_mode = trim($_POST['smtp_mode'] ?? 'direct');
    $smtp_host = trim($_POST['smtp_host'] ?? 'mail');
    $smtp_port = trim($_POST['smtp_port'] ?? '25');
    $smtp_user = trim($_POST['smtp_user'] ?? '');
    $smtp_pass = trim($_POST['smtp_pass'] ?? '');
    $smtp_secure = trim($_POST['smtp_secure'] ?? 'none');
    $alert_email = trim($_POST['alert_email'] ?? '');
    $webmon_url = trim($_POST['webmon_url'] ?? '');

    $retention_days = (int)($_POST['retention_days'] ?? 30);
    if ($retention_days < 1) $retention_days = 30;

    $def_email_mode = trim($_POST['def_email_mode'] ?? 'none');
    $def_sender_email = trim($_POST['def_sender_email'] ?? '');
    $def_receiver_email = trim($_POST['def_receiver_email'] ?? '');
    $def_check = trim($_POST['def_check'] ?? 'alarm');
    $def_ssl_mon = isset($_POST['def_ssl_mon']) ? '1' : '0';
    $def_dom_mon = isset($_POST['def_dom_mon']) ? '1' : '0';
    $def_engine_type = trim($_POST['def_engine_type'] ?? 'lynx');
    $def_retry_count = (int)($_POST['def_retry_count'] ?? 1);
    $def_timeout_sec = (int)($_POST['def_timeout_sec'] ?? 2);
    $def_mute_hours = (int)($_POST['def_mute_hours'] ?? 2);
    if ($def_mute_hours < 0) $def_mute_hours = 0;

    $alert_format = trim($_POST['alert_format'] ?? 'plain');
    $email_template_html = $_POST['email_template_html'] ?? '';
    $tg_template_html = $_POST['tg_template_html'] ?? '';

    // 민감 정보는 .env 파일에 저장
    require_once __DIR__ . '/classes/Utils.php';
    $env_updates = [
        'TG_TOKEN' => $tg_token,
        'TG_CHAT_ID' => $tg_chat_id,
        'SMTP_HOST' => $smtp_host,
        'SMTP_PORT' => $smtp_port,
        'SMTP_USER' => $smtp_user,
        'SMTP_SECURE' => $smtp_secure,
        'WEBMON_URL' => $webmon_url
    ];
    if (!empty($smtp_pass)) {
        $env_updates['SMTP_PASS'] = $smtp_pass;
    }
    Utils::writeEnv($env_updates);

    $stmt = $db->prepare("INSERT INTO system_settings (key_name, key_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");

    $updates = [
        'tg_enabled' => $tg_enabled,
        'email_enabled' => $email_enabled,
        'smtp_mode' => $smtp_mode,
        'alert_email' => $alert_email,
        'retention_days' => (string)$retention_days,
        'def_email_mode' => $def_email_mode,
        'def_sender_email' => $def_sender_email,
        'def_receiver_email' => $def_receiver_email,
        'def_check' => $def_check,
        'def_ssl_mon' => $def_ssl_mon,
        'def_dom_mon' => $def_dom_mon,
        'def_engine_type' => $def_engine_type,
        'def_retry_count' => (string)$def_retry_count,
        'def_timeout_sec' => (string)$def_timeout_sec,
        'def_mute_hours' => (string)$def_mute_hours,
        'alert_format' => $alert_format,
        'email_template_html' => $email_template_html,
        'tg_template_html' => $tg_template_html
    ];

    foreach ($updates as $k => $v) {
        $stmt->bind_param("ss", $k, $v);
        $stmt->execute();
    }

    $stmt->close();

    // 민감 정보는 system_settings 테이블에서 삭제하여 .env에서만 관리되도록 함
    $sensitive_keys = ['tg_token', 'tg_chat_id', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_secure', 'webmon_url'];
    $placeholders = implode(',', array_fill(0, count($sensitive_keys), '?'));
    $del_stmt = $db->prepare("DELETE FROM system_settings WHERE key_name IN ($placeholders)");
    if ($del_stmt) {
        $types = str_repeat('s', count($sensitive_keys));
        $del_stmt->bind_param($types, ...$sensitive_keys);
        $del_stmt->execute();
        $del_stmt->close();
    }
}

header("Location: website_status.php?settings_saved=1");
exit;
?>