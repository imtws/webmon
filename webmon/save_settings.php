<?php
require_once 'config/database.php';

function redirectSettingsError($error) {
    header("Location: website_status.php?settings_error=" . rawurlencode($error));
    exit;
}

function setCurrentDatabasePassword(mysqli $connection, string $password) {
    $connection->set_charset('utf8mb4');
    $connection->query("SET SESSION sql_mode = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', @@SESSION.sql_mode, ','), ',NO_BACKSLASH_ESCAPES,', ','))");
    $escaped_password = $connection->real_escape_string($password);
    $connection->query("SET PASSWORD = PASSWORD('$escaped_password')");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $global_sender_email = trim($_POST['global_sender_email'] ?? '');
    if ($global_sender_email !== '' && !filter_var($global_sender_email, FILTER_VALIDATE_EMAIL)) {
        redirectSettingsError('sender_email_invalid');
    }

    // Validate and prepare password changes before changing the database credential.
    $db_pass_old = $_POST['db_pass_old'] ?? '';
    $db_pass_new = $_POST['db_pass_new'] ?? '';

    if ($db_pass_old !== '' || $db_pass_new !== '') {
        if ($db_pass_new === '') {
            redirectSettingsError('db_pass_required');
        }
        if ($db_pass_old === '') {
            $defaultDbPassword = 'your_secure_password_here';
            if (hash_equals($defaultDbPassword, $db_pass)) {
                $db_pass_old = $db_pass;
            } else {
                redirectSettingsError('db_pass_required');
            }
        }

        $db_host = getenv('DB_HOST') ?: 'localhost';
        $db_user = getenv('DB_USER') ?: 'sitemonitor';
        $db_name = getenv('DB_NAME') ?: 'sitemonitor';
        $db_port = (int)(getenv('DB_PORT') ?: 3306);
        $runtime_dir = '/var/lib/webmon';
        $runtime_pass_file = $runtime_dir . '/db_pass';

        if (!is_dir($runtime_dir) || !is_writable($runtime_dir)) {
            redirectSettingsError('db_pass_storage_failed');
        }

        $temp_pass_file = tempnam($runtime_dir, '.db-pass-');
        $bytes_written = $temp_pass_file === false
            ? false
            : file_put_contents($temp_pass_file, $db_pass_new, LOCK_EX);
        if ($temp_pass_file === false || $bytes_written !== strlen($db_pass_new)) {
            if ($temp_pass_file !== false) {
                unlink($temp_pass_file);
            }
            redirectSettingsError('db_pass_storage_failed');
        }
        chmod($temp_pass_file, 0600);

        try {
            $test_conn = new mysqli($db_host, $db_user, $db_pass_old, $db_name, $db_port);
        } catch (mysqli_sql_exception $e) {
            unlink($temp_pass_file);
            redirectSettingsError('db_pass_incorrect');
        }

        try {
            setCurrentDatabasePassword($test_conn, $db_pass_new);
        } catch (mysqli_sql_exception $e) {
            unlink($temp_pass_file);
            $test_conn->close();
            redirectSettingsError('db_alter_failed');
        }

        if (!rename($temp_pass_file, $runtime_pass_file)) {
            try {
                setCurrentDatabasePassword($test_conn, $db_pass_old);
            } catch (mysqli_sql_exception $e) {
                error_log('Database password rollback failed after runtime credential storage error.');
            }
            unlink($temp_pass_file);
            $test_conn->close();
            redirectSettingsError('db_pass_storage_failed');
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

    $log_max_lines = (int)($_POST['log_max_lines'] ?? 100);
    $log_max_lines = min(100, max(20, $log_max_lines));

    $log_rotate_mb = (int)($_POST['log_rotate_mb'] ?? 50);
    if ($log_rotate_mb < 1) $log_rotate_mb = 50;

    $alert_format = trim($_POST['alert_format'] ?? 'plain');
    $email_template_html = $_POST['email_template_html'] ?? '';
    $email_template_plain = $_POST['email_template_plain'] ?? '';
    $tg_template_html = $_POST['tg_template_html'] ?? '';

    // Keep credentials outside the database and source tree.
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
        'global_sender_email' => $global_sender_email,
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
        'log_max_lines' => (string)$log_max_lines,
        'log_rotate_mb' => (string)$log_rotate_mb,
        'alert_format' => $alert_format,
        'email_template_html' => $email_template_html,
        'email_template_plain' => $email_template_plain,
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