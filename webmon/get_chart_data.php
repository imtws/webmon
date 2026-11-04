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
        'period_start' => $row['log_time'],
        'period_end' => $row['log_time'],
        'response_time' => (float)$row['response_time'],
        'result' => (int)$row['result'],
        'sample_count' => 1,
        'failure_count' => (int)$row['result'] === 0 ? 1 : 0
    ];
}

$stmt->close();

// 📈 로그 다운샘플링 (최대 300포인트로 압축하여 차트 버벅임 및 메모리 낭비 예방)
$total_count = count($data);
$max_points = 300;

if ($total_count > $max_points) {
    $downsampled = [];
    $bucket_size = $total_count / $max_points;

    for ($i = 0; $i < $max_points; $i++) {
        $start_idx = (int)floor($i * $bucket_size);
        $end_idx = (int)floor(($i + 1) * $bucket_size) - 1;
        if ($end_idx >= $total_count) {
            $end_idx = $total_count - 1;
        }

        $sum_rt = 0;
        $min_res = 1; // 1 = 성공, 0 = 실패 (구간 중 한 번이라도 실패하면 실패 처리하여 가시성 확보)
        $failure_count = 0;
        $bucket_count = 0;

        for ($j = $start_idx; $j <= $end_idx; $j++) {
            $sum_rt += $data[$j]['response_time'];
            if ($data[$j]['result'] === 0) {
                $min_res = 0;
                $failure_count++;
            }
            $bucket_count++;
        }

        $mid_idx = (int)floor(($start_idx + $end_idx) / 2);
        $downsampled[] = [
            'time' => $data[$mid_idx]['time'],
            'period_start' => $data[$start_idx]['time'],
            'period_end' => $data[$end_idx]['time'],
            'response_time' => $bucket_count > 0 ? (float)($sum_rt / $bucket_count) : 0.0,
            'result' => $min_res,
            'sample_count' => $bucket_count,
            'failure_count' => $failure_count
        ];
    }
    $data = $downsampled;
}

echo json_encode($data);
exit;
?>