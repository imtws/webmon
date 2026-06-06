<?php
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tg_enabled = isset($_POST['tg_enabled']) ? '1' : '0';
    $tg_token = trim($_POST['tg_token'] ?? '');
    $tg_chat_id = trim($_POST['tg_chat_id'] ?? '');

    $email_enabled = isset($_POST['email_enabled']) ? '1' : '0';
    $smtp_host = trim($_POST['smtp_host'] ?? 'mail');
    $smtp_port = trim($_POST['smtp_port'] ?? '25');
    $smtp_user = trim($_POST['smtp_user'] ?? '');
    $smtp_pass = trim($_POST['smtp_pass'] ?? '');
    $smtp_secure = trim($_POST['smtp_secure'] ?? 'none');
    $alert_email = trim($_POST['alert_email'] ?? '');

    $mute_enabled = isset($_POST['mute_enabled']) ? '1' : '0';
    $mute_start_time = trim($_POST['mute_start_time'] ?? '22:00');
    $mute_end_time = trim($_POST['mute_end_time'] ?? '07:00');

    $retention_days = (int)($_POST['retention_days'] ?? 30);
    if ($retention_days < 1) $retention_days = 30;

    $stmt = $db->prepare("INSERT INTO system_settings (key_name, key_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");

    $updates = [
        'tg_enabled' => $tg_enabled,
        'tg_token' => $tg_token,
        'tg_chat_id' => $tg_chat_id,
        'email_enabled' => $email_enabled,
        'smtp_host' => $smtp_host,
        'smtp_port' => $smtp_port,
        'smtp_user' => $smtp_user,
        'smtp_secure' => $smtp_secure,
        'alert_email' => $alert_email,
        'mute_enabled' => $mute_enabled,
        'mute_start_time' => $mute_start_time,
        'mute_end_time' => $mute_end_time,
        'retention_days' => (string)$retention_days
    ];

    if (!empty($smtp_pass)) {
        $updates['smtp_pass'] = $smtp_pass;
    }

    foreach ($updates as $k => $v) {
        $stmt->bind_param("ss", $k, $v);
        $stmt->execute();
    }

    $stmt->close();
}

header("Location: website_status.php?settings_saved=1");
exit;
?>