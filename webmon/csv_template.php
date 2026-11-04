<?php
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="webmon-monitor-template.csv"');
header('X-Content-Type-Options: nosniff');

$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, [
    'company', 'url', 'contents', 'group_name', 'check', 'ssl_mon', 'dom_mon',
    'comment', 'engine_type', 'retry_count', 'timeout_sec', 'email_mode',
    'sender_email', 'receiver_email'
], ',', '"', '');
fputcsv($output, [
    'Example site', 'https://example.com', 'Example', 'Default group', 'alarm',
    '1', '1', '', 'curl', '1', '5', 'none', '', ''
], ',', '"', '');
fclose($output);
