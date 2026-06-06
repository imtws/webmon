<?php
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $no = isset($_POST['no']) ? (int)$_POST['no'] : 0;
    if ($no > 0) {
        $sched_enabled = isset($_POST['sched_enabled']) ? 1 : 0;
        $sched_start_time = trim($_POST['sched_start_time'] ?? '09:00');
        $sched_end_time = trim($_POST['sched_end_time'] ?? '18:00');
        $sched_mode = trim($_POST['sched_mode'] ?? 'active');

        $stmt = $db->prepare("UPDATE monitor_conf SET sched_enabled = ?, sched_start_time = ?, sched_end_time = ?, sched_mode = ? WHERE no = ?");
        $stmt->bind_param("isssi", $sched_enabled, $sched_start_time, $sched_end_time, $sched_mode, $no);
        $stmt->execute();
        $stmt->close();
    }
}

header("Location: website_status.php?settings_saved=1");
exit;
?>
