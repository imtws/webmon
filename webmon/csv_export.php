<?php
require_once 'config/database.php';

$columns = [
    'company',
    'url',
    'contents',
    'group_name',
    'check',
    'ssl_mon',
    'dom_mon',
    'comment',
    'engine_type',
    'retry_count',
    'timeout_sec',
    'email_mode',
    'sender_email',
    'receiver_email'
];

$result = $db->query(
    'SELECT company, url, contents, group_name, `check`, ssl_mon, dom_mon, comment, ' .
    'engine_type, retry_count, timeout_sec, email_mode, sender_email, receiver_email ' .
    'FROM monitor_conf ORDER BY no ASC'
);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="webmon-monitor-settings-' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

$output = fopen('php://output', 'w');
if ($output === false) {
    http_response_code(500);
    error_log('Could not open output stream for monitor CSV export.');
    exit;
}

fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, $columns, ',', '"', '');
while ($row = $result->fetch_assoc()) {
    fputcsv($output, array_map(
        static fn(string $column) => $row[$column] ?? '',
        $columns
    ), ',', '"', '');
}
fclose($output);
