<?php
header('Content-Type: application/json');
require_once 'config/database.php';

$no = isset($_GET['no']) ? (int)$_GET['no'] : 0;
$range = isset($_GET['range']) ? $_GET['range'] : '1d';

if ($no <= 0) {
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

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