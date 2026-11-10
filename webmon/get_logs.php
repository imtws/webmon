<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/language.php';
require_once __DIR__ . '/classes/Utils.php';
require_once __DIR__ . '/classes/LogModel.php';

$log_data = LogModel::fetchLogs($db, $_GET, $settings);
$log_rows = $log_data['rows'];
$log_has_more = $log_data['has_more'];
$next_log_cursor = $log_data['next_cursor'];
$t = $lang_pack[$current_lang] ?? $lang_pack['KO'];

include __DIR__ . '/views/components/log_view.php';
