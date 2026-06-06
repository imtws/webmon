<?php
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $no = isset($_POST['no']) ? (int)$_POST['no'] : 0;
    if ($no > 0) {
        $sched_enabled = isset($_POST['sched_enabled']) ? 1 : 0;
        $mute_until = null;
        if ($sched_enabled && !empty($_POST['mute_until'])) {
            $mute_until = str_replace('T', ' ', $_POST['mute_until']) . ':00';
        }

        $stmt = $db->prepare("UPDATE monitor_conf SET sched_enabled = ?, mute_until = ? WHERE no = ?");
        $stmt->bind_param("isi", $sched_enabled, $mute_until, $no);
        $stmt->execute();
        $stmt->close();
    }
}

header("Location: website_status.php?settings_saved=1");
exit;
?>
