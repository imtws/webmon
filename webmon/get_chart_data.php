<?php
header('Content-Type: application/json');
require_once 'config/database.php';

$no    = isset($_GET['no'])    ? (int)$_GET['no']        : 0;
$range = isset($_GET['range']) ? $_GET['range']           : '1d';
$start = isset($_GET['start']) ? trim($_GET['start'])     : '';
$end   = isset($_GET['end'])   ? trim($_GET['end'])       : '';
// 차트에 표시할 최대 포인트 수 (클라이언트가 조정 가능)
$max_points = isset($_GET['max']) ? max(20, min(500, (int)$_GET['max'])) : 300;

if ($no <= 0) {
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

// 조회 범위 결정
if (!empty($start) && !empty($end)) {
    // 커스텀 범위
    $start = str_replace('T', ' ', $start);
    $end   = str_replace('T', ' ', $end);

    $start_full = null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start))                  $start_full = $start . ' 00:00:00';
    elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $start)) $start_full = $start . ':00';
    elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $start)) $start_full = $start;

    $end_full = null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $end))                    $end_full = $end . ' 23:59:59';
    elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $end))   $end_full = $end . ':59';
    elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $end)) $end_full = $end;

    if (!$start_full || !$end_full) {
        echo json_encode(['error' => 'Invalid date range']);
        exit;
    }

    // 커스텀 범위일 때 버킷 크기: 전체 범위 / max_points (초 단위)
    $seconds   = max(1, strtotime($end_full) - strtotime($start_full));
    $bucket_s  = max(60, (int)ceil($seconds / $max_points));

    $sql = "
        SELECT
          FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(log_time) / {$bucket_s}) * {$bucket_s}) AS bucket_time,
          AVG(response_time)                                AS response_time,
          MIN(result)                                       AS result,
          COUNT(*)                                          AS sample_count,
          SUM(CASE WHEN result = 0 THEN 1 ELSE 0 END)      AS failure_count,
          MIN(log_time)                                     AS period_start,
          MAX(log_time)                                     AS period_end
        FROM monitor_log FORCE INDEX (idx_conf_no_log_time)
        WHERE monitor_conf_no = ?
          AND log_time >= ?
          AND log_time <= ?
        GROUP BY bucket_time
        ORDER BY bucket_time ASC
    ";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("iss", $no, $start_full, $end_full);

} else {
    // 사전 정의된 범위
    $interval_map = [
        '1h'  => ['seconds' => 3600,       'interval' => '1 HOUR'],
        '1d'  => ['seconds' => 86400,      'interval' => '1 DAY'],
        '7d'  => ['seconds' => 604800,     'interval' => '7 DAY'],
        '30d' => ['seconds' => 2592000,    'interval' => '30 DAY'],
    ];
    $map      = $interval_map[$range] ?? $interval_map['1d'];
    $interval = $map['interval'];
    $seconds  = $map['seconds'];

    // 버킷 크기: 범위 / max_points (최소 60초)
    $bucket_s = max(60, (int)ceil($seconds / $max_points));

    $sql = "
        SELECT
          FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(log_time) / {$bucket_s}) * {$bucket_s}) AS bucket_time,
          AVG(response_time)                                AS response_time,
          MIN(result)                                       AS result,
          COUNT(*)                                          AS sample_count,
          SUM(CASE WHEN result = 0 THEN 1 ELSE 0 END)      AS failure_count,
          MIN(log_time)                                     AS period_start,
          MAX(log_time)                                     AS period_end
        FROM monitor_log FORCE INDEX (idx_conf_no_log_time)
        WHERE monitor_conf_no = ?
          AND log_time >= DATE_SUB(NOW(), INTERVAL {$interval})
        GROUP BY bucket_time
        ORDER BY bucket_time ASC
    ";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $no);
}

$stmt->execute();
$res  = $stmt->get_result();
$data = [];

while ($row = $res->fetch_assoc()) {
    $data[] = [
        'time'          => $row['bucket_time'],
        'period_start'  => $row['period_start'],
        'period_end'    => $row['period_end'],
        'response_time' => round((float)$row['response_time'], 4),
        'result'        => (int)$row['result'],
        'sample_count'  => (int)$row['sample_count'],
        'failure_count' => (int)$row['failure_count'],
    ];
}

$stmt->close();

echo json_encode($data);
exit;
