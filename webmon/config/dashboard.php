<?php
// [2] 검색 및 필터 쿼리 구성
$type = $_GET['type'] ?? 'total';
$search_company = $_GET['search_company'] ?? '';
$search_group = $_GET['search_group'] ?? 'all';
$filter_status = $_GET['filter_status'] ?? 'all';
$sort = $_GET['sort'] ?? 'latest';

$where = ["1=1"];
$filter_no = isset($_GET['filter_no']) ? (int)$_GET['filter_no'] : 0;
if ($filter_no > 0) {
    $where[] = "no = $filter_no";
} else {
    if ($type == 'success') $where[] = "`check` = 'alarm' AND value > 0";
    elseif ($type == 'failure') $where[] = "value = 0 AND `check` != 'disable'";
    elseif ($type == 'expiring') {
        $where[] = "`check` != 'disable' AND (
            (ssl_mon = 1 AND ssl_expiry IS NOT NULL AND ssl_expiry <= DATE_ADD(NOW(), INTERVAL 30 DAY))
            OR
            (dom_mon = 1 AND dom_expiry IS NOT NULL AND dom_expiry <= DATE_ADD(NOW(), INTERVAL 30 DAY))
        )";
    }
}

if (!empty($search_company)) {
    $safe_comp = $db->real_escape_string($search_company);
    $regex = Utils::buildChosungRegex($search_company);
    
    if ($regex !== false) {
        $safe_regex = $db->real_escape_string($regex);
        $where[] = "(company LIKE '%$safe_comp%' OR url LIKE '%$safe_comp%' OR company REGEXP '$safe_regex')";
    } else {
        $where[] = "(company LIKE '%$safe_comp%' OR url LIKE '%$safe_comp%')";
    }
}

if ($search_group !== 'all') {
    $safe_grp = $db->real_escape_string($search_group);
    $where[] = "group_name = '$safe_grp'";
}

if ($filter_status !== 'all') {
    $safe_status = $db->real_escape_string($filter_status);
    if ($safe_status === 'cross_on') {
        $where[] = "cross_check = 1";
    } elseif ($safe_status === 'cross_off') {
        $where[] = "cross_check = 0";
    } elseif ($safe_status === 'timeout_custom') {
        $where[] = "timeout_sec > 2";
    } elseif ($safe_status === 'timeout_default') {
        $where[] = "timeout_sec <= 2";
    } else {
        $where[] = "`check` = '$safe_status'";
    }
}

$filter_engine = $_GET['filter_engine'] ?? 'all';

if ($filter_engine === 'lynx') {
    $where[] = "(engine_type = 'lynx' OR engine_type IS NULL OR engine_type = '')";
} elseif ($filter_engine === 'curl') {
    $where[] = "engine_type = 'curl'";
}

$where_sql = implode(' AND ', $where);

$order_query = "ORDER BY no DESC";
if ($sort == 'comp_asc') {
    $order_query = "ORDER BY company ASC, no DESC";
} elseif ($sort == 'comp_desc') {
    $order_query = "ORDER BY company DESC, no DESC";
} elseif ($sort == 'ssl') {
    $order_query = "ORDER BY ssl_expiry ASC";
} elseif ($sort == 'dom') {
    $order_query = "ORDER BY dom_expiry ASC";
} elseif ($sort == 'status_desc') {
    $order_query = "ORDER BY CASE TRIM(`check`) WHEN 'alarm' THEN 1 WHEN '1' THEN 1 WHEN 'no alarm' THEN 2 WHEN '2' THEN 2 WHEN 'disable' THEN 3 WHEN '0' THEN 3 WHEN 'fail' THEN 3 ELSE 4 END ASC, no DESC";
} elseif ($sort == 'status_asc') {
    $order_query = "ORDER BY CASE TRIM(`check`) WHEN 'disable' THEN 1 WHEN '0' THEN 1 WHEN 'fail' THEN 1 WHEN 'no alarm' THEN 2 WHEN '2' THEN 2 WHEN 'alarm' THEN 3 WHEN '1' THEN 3 ELSE 4 END ASC, no DESC";
} elseif ($sort == 'result_succ') {
    $order_query = "ORDER BY CASE WHEN `check`='disable' THEN 3 WHEN value>0 THEN 1 ELSE 2 END ASC, no DESC";
} elseif ($sort == 'result_fail') {
    $order_query = "ORDER BY CASE WHEN `check`='disable' THEN 3 WHEN value=0 THEN 1 ELSE 2 END ASC, no DESC";
} elseif ($sort == 'retry_desc') {
    $order_query = "ORDER BY retry_count DESC, no DESC";
} elseif ($sort == 'retry_asc') {
    $order_query = "ORDER BY retry_count ASC, no DESC";
}

$summary = $db->query("SELECT COUNT(*) AS total,
    COUNT(CASE WHEN `check` = 'alarm' AND value > 0 THEN 1 END) AS success,
    COUNT(CASE WHEN value = 0 AND `check` != 'disable' THEN 1 END) AS failure,
    COUNT(CASE WHEN `check` != 'disable' AND (
        (ssl_mon = 1 AND ssl_expiry IS NOT NULL AND ssl_expiry <= DATE_ADD(NOW(), INTERVAL 30 DAY))
        OR
        (dom_mon = 1 AND dom_expiry IS NOT NULL AND dom_expiry <= DATE_ADD(NOW(), INTERVAL 30 DAY))
    ) THEN 1 END) AS expiring,
    MAX(last_checked_at) AS last_run
    FROM monitor_conf")->fetch_assoc();
$stat_total = $summary['total'];
$stat_success = $summary['success'];
$stat_fail = $summary['failure'];
$stat_expiring = $summary['expiring'];
$last_run = !empty($summary['last_run']) ? $summary['last_run'] : '-';
$next_run = $last_run !== '-' ? date('Y-m-d H:i:s', strtotime($last_run . ' + 1 minutes')) : '-';

$result = $db->query("SELECT * FROM monitor_conf WHERE $where_sql $order_query");
