<?php
header('Content-Type: application/json');
require_once 'config/database.php';

$no = isset($_GET['no']) ? (int)$_GET['no'] : 0;
$range = isset($_GET['range']) ? $_GET['range'] : '1d';
$start = isset($_GET['start']) ? $_GET['start'] : '';
$end = isset($_GET['end']) ? $_GET['end'] : '';

if ($no <= 0) {
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

if (!empty($start) && !empty($end)) {
    $start = str_replace('T', ' ', $start);
    $end = str_replace('T', ' ', $end);

    $start_full = null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
        $start_full = $start . ' 00:00:00';
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $start)) {
        $start_full = $start . ':00';
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $start)) {
        $start_full = $start;
    }

    $end_full = null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        $end_full = $end . ' 23:59:59';
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $end)) {
        $end_full = $end . ':59';
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $end)) {
        $end_full = $end;
    }

    if ($start_full && $end_full) {
        $stmt = $db->prepare("
            SELECT log_time, response_time, result 
            FROM monitor_log 
            WHERE monitor_conf_no = ? 
              AND log_time >= ? 
              AND log_time <= ?
            ORDER BY log_time ASC
        ");
        $stmt->bind_param("iss", $no, $start_full, $end_full);
    } else {
        $stmt = null;
    }
} else {
    $stmt = null;
}

if (!$stmt) {
    $interval = '1 DAY';
    if ($range === '7d') {
        $interval = '7 DAY';
    } elseif ($range === '30d') {
        $interval = '30 DAY';
    }
    $stmt = $db->prepare("
        SELECT log_time, response_time, result 
        FROM monitor_log 
        WHERE monitor_conf_no = ? 
          AND log_time >= DATE_SUB(NOW(), INTERVAL $interval)
        ORDER BY log_time ASC
    ");
    $stmt->bind_param("i", $no);
}

$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = [
        'time' => $row['log_time'],
        'response_time' => (float)$row['response_time'],
        'result' => (int)$row['result']
    ];
}

$stmt->close();

echo json_encode($data);
exit;
?>