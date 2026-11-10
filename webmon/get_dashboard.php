<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/language.php';
require_once __DIR__ . '/views/components/expiry.php';

if (!in_array($current_view, ['card', 'list'], true)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Invalid dashboard view']);
    exit;
}

require __DIR__ . '/config/dashboard.php';

ob_start();
require __DIR__ . '/views/components/monitor_items.php';
$items = ob_get_clean();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode([
    'items' => $items,
    'statistics' => [
        'statTotal' => $stat_total,
        'statSuccess' => $stat_success,
        'statFail' => $stat_fail,
        'statExpiring' => $stat_expiring,
        'lastRunValue' => $last_run,
        'nextRunValue' => $next_run,
    ],
], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
