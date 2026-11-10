<?php
require_once 'config/database.php';

function redirectSettingsError($error) {
    header("Location: website_status.php?settings_error=" . rawurlencode($error));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 변경 전 전역 설정 스냅샷 (job_history 기록용)
    require_once __DIR__ . '/classes/Utils.php';
    $before_settings = [];
    $snap_res = $db->query("SELECT key_name, key_value FROM system_settings ORDER BY key_name");
    if ($snap_res) {
        while ($sr = $snap_res->fetch_assoc()) {
            $before_settings[$sr['key_name']] = $sr['key_value'];
        }
    }
    // .env 민감 설정도 스냅샷에 포함 (비밀번호는 마스킹)
    $env_now = Utils::readEnv();
    foreach (['TG_TOKEN','TG_CHAT_ID','SMTP_HOST','SMTP_PORT','SMTP_USER','SMTP_SECURE','WEBMON_URL'] as $ek) {
        $before_settings['_env_' . strtolower($ek)] = $env_now[$ek] ?? '';
    }
    $before_settings['_env_smtp_pass'] = !empty($env_now['SMTP_PASS']) ? '••••••••' : '';


    $global_sender_email = trim($_POST['global_sender_email'] ?? '');
    if ($global_sender_email !== '' && !filter_var($global_sender_email, FILTER_VALIDATE_EMAIL)) {
        redirectSettingsError('sender_email_invalid');
    }

    $tg_enabled = isset($_POST['tg_enabled']) ? '1' : '0';
    $tg_token = trim($_POST['tg_token'] ?? '');
    $tg_chat_id = trim($_POST['tg_chat_id'] ?? '');

    $email_enabled = isset($_POST['email_enabled']) ? '1' : '0';
    $smtp_mode = isset($_POST['smtp_relay_enabled']) ? 'relay' : 'direct';
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

    $alert_concurrency = (int)($_POST['alert_concurrency'] ?? 60);
    $alert_concurrency = min(200, max(5, $alert_concurrency));

    $alert_format = trim($_POST['alert_format'] ?? 'plain');
    $email_subject = trim(str_replace(["\r", "\n"], ' ', (string)($_POST['email_subject'] ?? '')));
    if ($email_subject === '') $email_subject = '[WebSiteMonitor] 웹사이트 모니터링 알람 발생 {YYYY-MM-DD}';
    $report_enabled = isset($_POST['report_enabled']) ? '1' : '0';
    $report_days = array_values(array_unique(array_filter(
        array_map('intval', (array)($_POST['report_weekdays'] ?? [])),
        fn($d) => $d >= 0 && $d <= 6
    )));
    sort($report_days);
    if (!$report_days) $report_days = [1];
    $report_time = trim((string)($_POST['report_time'] ?? '09:00'));
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $report_time)) $report_time = '09:00';
    $report_within_days = min(365, max(1, (int)($_POST['report_within_days'] ?? 30)));
    $report_receiver_email = trim((string)($_POST['report_receiver_email'] ?? ''));
    if ($report_receiver_email !== '' && !filter_var($report_receiver_email, FILTER_VALIDATE_EMAIL)) $report_receiver_email = '';
    $email_template_html = $_POST['email_template_html'] ?? '';
    $email_template_plain = $_POST['email_template_plain'] ?? '';
    $tg_template_plain = $_POST['tg_template_plain'] ?? '';

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
        'alert_concurrency' => (string)$alert_concurrency,
        'alert_format' => $alert_format,
        'email_subject' => $email_subject,
        'report_enabled' => $report_enabled,
        'report_weekdays' => implode(',', $report_days),
        'report_time' => $report_time,
        'report_within_days' => (string)$report_within_days,
        'report_receiver_email' => $report_receiver_email,
        'email_template_html' => $email_template_html,
        'email_template_plain' => $email_template_plain,
        'tg_template_plain' => $tg_template_plain
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

    // 변경 후 설정 스냅샷 수집
    $after_settings = [];
    $snap_after = $db->query("SELECT key_name, key_value FROM system_settings ORDER BY key_name");
    if ($snap_after) {
        while ($ar = $snap_after->fetch_assoc()) {
            $after_settings[$ar['key_name']] = $ar['key_value'];
        }
    }
    $env_after = Utils::readEnv();
    foreach (['TG_TOKEN','TG_CHAT_ID','SMTP_HOST','SMTP_PORT','SMTP_USER','SMTP_SECURE','WEBMON_URL'] as $ek) {
        $after_settings['_env_' . strtolower($ek)] = $env_after[$ek] ?? '';
    }
    $after_settings['_env_smtp_pass'] = !empty($env_after['SMTP_PASS']) ? '••••••••' : '';

    // 변경된 key 목록 추출
    $all_keys = array_unique(array_merge(array_keys($before_settings), array_keys($after_settings)));
    $changed_keys = [];
    foreach ($all_keys as $k) {
        if (($before_settings[$k] ?? '') !== ($after_settings[$k] ?? '')) {
            $changed_keys[] = $k;
        }
    }

    // job_history에 전역 설정 변경 이력 기록 (실제 변경사항이 있을 때만)
    if (!empty($changed_keys)) {
        $hist_snapshot = json_encode([
            'type'   => 'settings_update',
            'before' => $before_settings,
            'after'  => $after_settings,
        ], JSON_UNESCAPED_UNICODE);
        $hist_changed = implode(', ', $changed_keys);
        $hist_stmt = $db->prepare(
            "INSERT INTO job_history (action_type, target_count, changed_fields, snapshot) VALUES ('settings_update', 1, ?, ?)"
        );
        if ($hist_stmt) {
            $hist_stmt->bind_param("ss", $hist_changed, $hist_snapshot);
            $hist_stmt->execute();
            $hist_stmt->close();
        }
    }
}

header("Location: website_status.php?settings_saved=1");
exit;
?>