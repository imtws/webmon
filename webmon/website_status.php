<?php
// ==========================================
// [0] 환경 설정 및 공통 파일 로드 (★ 이 부분이 빠져서 에러가 났던 겁니다!)
// ==========================================
require_once 'config/database.php';
require_once 'config/language.php';
require_once 'classes/Utils.php';

// ==========================================
// [1] DB 처리 로직
// ==========================================
$query_string = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';

if (isset($_GET['action']) && $_GET['action'] == 'rollback_job') {
    $job_id = (int)$_GET['job_id'];
    
    // 1. Fetch job details
    $job_res = $db->query("SELECT * FROM job_history WHERE id = $job_id AND is_rolled_back = 0");
    if ($job_res && $job = $job_res->fetch_assoc()) {
        $action_type = $job['action_type'];
        $snapshot = json_decode($job['snapshot'], true);
        
        if (is_array($snapshot)) {
            $db->query("SET FOREIGN_KEY_CHECKS = 0;");
            
            if ($action_type === 'bulk_update') {
                // Restore each item by update
                foreach ($snapshot as $item) {
                    $no = (int)$item['no'];
                    $updates = [];
                    foreach ($item as $key => $val) {
                        if ($key === 'no') continue;
                        if ($val === null) {
                            $updates[] = "`$key` = NULL";
                        } else {
                            $safe_val = $db->real_escape_string($val);
                            $updates[] = "`$key` = '$safe_val'";
                        }
                    }
                    $updates_str = implode(', ', $updates);
                    $db->query("UPDATE monitor_conf SET $updates_str WHERE no = $no");
                }
            } elseif ($action_type === 'bulk_delete' || $action_type === 'delete') {
                // Re-insert each item
                foreach ($snapshot as $item) {
                    $fields = [];
                    $values = [];
                    foreach ($item as $key => $val) {
                        $fields[] = "`$key`";
                        if ($val === null) {
                            $values[] = "NULL";
                        } else {
                            $safe_val = $db->real_escape_string($val);
                            $values[] = "'$safe_val'";
                        }
                    }
                    $fields_str = implode(', ', $fields);
                    $values_str = implode(', ', $values);
                    $db->query("INSERT INTO monitor_conf ($fields_str) VALUES ($values_str)");
                }
            }
            
            $db->query("SET FOREIGN_KEY_CHECKS = 1;");
            
            // Mark job as rolled back
            $rolled_back_time = date('Y-m-d H:i:s');
            $db->query("UPDATE job_history SET is_rolled_back = 1, rolled_back_time = '$rolled_back_time' WHERE id = $job_id");
            
            header("Location: website_status.php?msg=rollback_success");
            exit;
        }
    }
    header("Location: website_status.php?msg=rollback_failed");
    exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    $no = (int)$_GET['no'];
    
    // Capture snapshot for rollback
    $res = $db->query("SELECT * FROM monitor_conf WHERE no = $no");
    if ($res && $row = $res->fetch_assoc()) {
        $snapshot_json = json_encode([$row], JSON_UNESCAPED_UNICODE);
        $safe_snapshot = $db->real_escape_string($snapshot_json);
        $db->query("INSERT INTO job_history (action_type, target_count, changed_fields, snapshot) 
                    VALUES ('delete', 1, '', '$safe_snapshot')");
    }
    
    $db->query("DELETE FROM monitor_conf WHERE no = $no");
    
    $redirect_url = "website_status.php";
    $query_parts = [];
    if (!empty($_GET['current_query'])) {
        $query_parts[] = $_GET['current_query'];
    }
    $query_parts[] = "msg=delete_success";
    $redirect_url .= "?" . implode('&', $query_parts);
    header("Location: " . $redirect_url); 
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $redirect_url = "website_status.php";
    if (isset($_POST['current_query']) && !empty($_POST['current_query'])) {
        $redirect_url .= "?" . $_POST['current_query'];
    }

    if ($action === 'add' || $action === 'edit') {
        $company = $db->real_escape_string($_POST['company']);
        $group_select = $_POST['group_select'] ?? '';
        $group_name_new = trim($_POST['group_name_new'] ?? '');
        $final_group = ($group_select === '_NEW_' && $group_name_new !== '') ? $group_name_new : $group_select;
        if (empty($final_group)) $final_group = '기본 그룹';
        
        $group_name = $db->real_escape_string($final_group);
        $url = $db->real_escape_string($_POST['url']);
        $contents = $db->real_escape_string($_POST['contents']);
        $check = $db->real_escape_string($_POST['check']);
        $ssl_mon = (int)$_POST['ssl_mon'];
        $dom_mon = (int)$_POST['dom_mon'];
        $comment = $db->real_escape_string($_POST['comment'] ?? '');
        $sender_email = $db->real_escape_string($_POST['sender_email'] ?? '');
        $receiver_email = $db->real_escape_string($_POST['receiver_email'] ?? '');
        $email_mode = $db->real_escape_string($_POST['email_mode'] ?? 'none');
        $retry_count = (int)($_POST['retry_count'] ?? 1);
        $engine_type = $db->real_escape_string($_POST['engine_type'] ?? 'lynx');
        $timeout_sec = (int)($_POST['timeout_sec'] ?? 2);
        
        // 개별 SMTP 정보
        $smtp_host = $db->real_escape_string($_POST['smtp_host'] ?? '');
        $smtp_port = (int)($_POST['smtp_port'] ?? 25);
        $smtp_user = $db->real_escape_string($_POST['smtp_user'] ?? '');
        $smtp_pass = $db->real_escape_string($_POST['smtp_pass'] ?? '');
        $smtp_secure = $db->real_escape_string($_POST['smtp_secure'] ?? 'none');
        
        $mod_date = date('Y-m-d H:i:s');
        
        if ($action === 'add') {
            $sql = "INSERT INTO monitor_conf (company, group_name, url, contents, `check`, ssl_mon, dom_mon, comment, sender_email, receiver_email, email_mode, mod_date, value, response_time, retry_count, timeout_sec, engine_type, smtp_host, smtp_port, smtp_user, smtp_pass, smtp_secure) 
                    VALUES ('$company', '$group_name', '$url', '$contents', '$check', $ssl_mon, $dom_mon, '$comment', '$sender_email', '$receiver_email', '$email_mode', '$mod_date', 1, 0.0, $retry_count, $timeout_sec, '$engine_type', '$smtp_host', $smtp_port, '$smtp_user', '$smtp_pass', '$smtp_secure')";
        } else {
            $no = (int)$_POST['no'];
            $sql = "UPDATE monitor_conf SET 
                    company='$company', group_name='$group_name', url='$url', contents='$contents', `check`='$check', 
                    ssl_mon=$ssl_mon, dom_mon=$dom_mon, comment='$comment', sender_email='$sender_email', receiver_email='$receiver_email', email_mode='$email_mode', mod_date='$mod_date',
                    retry_count=$retry_count, timeout_sec=$timeout_sec, engine_type='$engine_type',
                    smtp_host='$smtp_host', smtp_port=$smtp_port, smtp_user='$smtp_user', smtp_pass='$smtp_pass', smtp_secure='$smtp_secure'
                    WHERE no=$no";
        }
        
        $db->query($sql);
        $msg_param = ($action === 'add') ? 'msg=add_success' : 'msg=edit_success';
        if (strpos($redirect_url, '?') !== false) {
            $redirect_url .= '&' . $msg_param;
        } else {
            $redirect_url .= '?' . $msg_param;
        }
        header("Location: " . $redirect_url); exit;
    }
    elseif ($action === 'bulk_delete' && !empty($_POST['ids'])) {
        $ids = array_map('intval', explode(',', $_POST['ids']));
        $ids_str = implode(',', $ids);
        
        // 원복용 사전 스냅샷 캡처
        $res = $db->query("SELECT * FROM monitor_conf WHERE no IN ($ids_str)");
        $snapshot_data = [];
        while ($row = $res->fetch_assoc()) {
            $snapshot_data[] = $row;
        }
        if (!empty($snapshot_data)) {
            $snapshot_json = json_encode($snapshot_data, JSON_UNESCAPED_UNICODE);
            $safe_snapshot = $db->real_escape_string($snapshot_json);
            $target_count = count($snapshot_data);
            $db->query("INSERT INTO job_history (action_type, target_count, changed_fields, snapshot) 
                        VALUES ('bulk_delete', $target_count, '', '$safe_snapshot')");
        }
        
        $db->query("DELETE FROM monitor_conf WHERE no IN ($ids_str)");
        if (strpos($redirect_url, '?') !== false) {
            $redirect_url .= '&msg=bulk_delete_success';
        } else {
            $redirect_url .= '?msg=bulk_delete_success';
        }
        header("Location: " . $redirect_url); exit;
    }
    elseif ($action === 'bulk_status' && !empty($_POST['ids'])) {
        $ids = array_map('intval', explode(',', $_POST['ids']));
        $ids_str = implode(',', $ids);
        $status = $db->real_escape_string($_POST['status']);
        
        // 일괄 무음 및 무음 해제 지원
        $def_hours_res = $db->query("SELECT key_value FROM system_settings WHERE key_name = 'def_mute_hours'");
        $def_hours = ($def_hours_res && $row_h = $def_hours_res->fetch_assoc()) ? (int)$row_h['key_value'] : 2;
        if ($def_hours < 0) $def_hours = 2;
        
        if ($status === 'alarm_unmute') {
            $db->query("UPDATE monitor_conf SET sched_enabled = 0, mute_until = NULL WHERE no IN ($ids_str)");
        } elseif ($status === 'alarm_mute_default') {
            $db->query("UPDATE monitor_conf SET sched_enabled = 1, mute_until = DATE_ADD(NOW(), INTERVAL $def_hours HOUR) WHERE no IN ($ids_str)");
        } else {
            $db->query("UPDATE monitor_conf SET `check`='$status' WHERE no IN ($ids_str)");
        }
        if (strpos($redirect_url, '?') !== false) {
            $redirect_url .= '&msg=bulk_status_success';
        } else {
            $redirect_url .= '?msg=bulk_status_success';
        }
        header("Location: " . $redirect_url); exit;
    }
    elseif ($action === 'bulk_update' && !empty($_POST['ids'])) {
        $ids = array_map('intval', explode(',', $_POST['ids']));
        $ids_str = implode(',', $ids);
        
        // 원복용 사전 스냅샷 캡처
        $res = $db->query("SELECT * FROM monitor_conf WHERE no IN ($ids_str)");
        $snapshot_data = [];
        while ($row = $res->fetch_assoc()) {
            $snapshot_data[] = $row;
        }
        
        $updates = [];
        $changed_fields_list = [];
        
        // 1. 그룹 설정 변경
        if (isset($_POST['chk_group'])) {
            $group_select = $_POST['group_select'] ?? '';
            $group_name_new = trim($_POST['group_name_new'] ?? '');
            $final_group = ($group_select === '_NEW_' && $group_name_new !== '') ? $group_name_new : $group_select;
            if (empty($final_group)) $final_group = '기본 그룹';
            $group_name = $db->real_escape_string($final_group);
            $updates[] = "group_name = '$group_name'";
            $changed_fields_list[] = "group_name";
        }
        
        // 2. 알람 상태 변경
        if (isset($_POST['chk_check'])) {
            $check = $db->real_escape_string($_POST['check']);
            $updates[] = "`check` = '$check'";
            $changed_fields_list[] = "check";
        }
        
        // 3. 실행 엔진 변경
        if (isset($_POST['chk_engine'])) {
            $engine_type = $db->real_escape_string($_POST['engine_type'] ?? 'lynx');
            $updates[] = "engine_type = '$engine_type'";
            $changed_fields_list[] = "engine_type";
        }
        
        // 4. 체크 횟수 및 타임아웃 변경
        if (isset($_POST['chk_limits'])) {
            $retry_count = (int)($_POST['retry_count'] ?? 1);
            $timeout_sec = (int)($_POST['timeout_sec'] ?? 2);
            $updates[] = "retry_count = $retry_count";
            $updates[] = "timeout_sec = $timeout_sec";
            $changed_fields_list[] = "retry_count, timeout_sec";
        }
        
        // 5. 만료일 모니터링 변경
        if (isset($_POST['chk_expiry'])) {
            $ssl_mon = (int)$_POST['ssl_mon'];
            $dom_mon = (int)$_POST['dom_mon'];
            $updates[] = "ssl_mon = $ssl_mon";
            $updates[] = "dom_mon = $dom_mon";
            $changed_fields_list[] = "ssl_mon, dom_mon";
        }
        
        // 6. 이메일 알림 설정 변경
        if (isset($_POST['chk_email'])) {
            $email_mode = $db->real_escape_string($_POST['email_mode'] ?? 'none');
            $sender_email = $db->real_escape_string($_POST['sender_email'] ?? '');
            $receiver_email = $db->real_escape_string($_POST['receiver_email'] ?? '');
            $updates[] = "email_mode = '$email_mode'";
            $updates[] = "sender_email = '$sender_email'";
            $updates[] = "receiver_email = '$receiver_email'";
            $changed_fields_list[] = "email_notification";
        }
        
        // 7. 개별 SMTP 서버 설정 변경
        if (isset($_POST['chk_smtp'])) {
            $smtp_host = $db->real_escape_string($_POST['smtp_host'] ?? '');
            $smtp_port = (int)($_POST['smtp_port'] ?? 25);
            $smtp_user = $db->real_escape_string($_POST['smtp_user'] ?? '');
            $smtp_pass = $db->real_escape_string($_POST['smtp_pass'] ?? '');
            $smtp_secure = $db->real_escape_string($_POST['smtp_secure'] ?? 'none');
            
            $updates[] = "smtp_host = '$smtp_host'";
            $updates[] = "smtp_port = $smtp_port";
            $updates[] = "smtp_user = '$smtp_user'";
            $updates[] = "smtp_pass = '$smtp_pass'";
            $updates[] = "smtp_secure = '$smtp_secure'";
            $changed_fields_list[] = "smtp_server_settings";
        }
        
        if (!empty($updates)) {
            // 이력 데이터베이스 기록
            if (!empty($snapshot_data)) {
                $snapshot_json = json_encode($snapshot_data, JSON_UNESCAPED_UNICODE);
                $safe_snapshot = $db->real_escape_string($snapshot_json);
                $changed_fields_str = implode(', ', $changed_fields_list);
                $safe_changed = $db->real_escape_string($changed_fields_str);
                $target_count = count($snapshot_data);
                $db->query("INSERT INTO job_history (action_type, target_count, changed_fields, snapshot) 
                            VALUES ('bulk_update', $target_count, '$safe_changed', '$safe_snapshot')");
            }
            
            $mod_date = date('Y-m-d H:i:s');
            $updates[] = "mod_date = '$mod_date'";
            $updates_str = implode(', ', $updates);
            $db->query("UPDATE monitor_conf SET $updates_str WHERE no IN ($ids_str)");
        }
        
        $msg_param = 'msg=bulk_edit_success';
        if (strpos($redirect_url, '?') !== false) {
            $redirect_url .= '&' . $msg_param;
        } else {
            $redirect_url .= '?' . $msg_param;
        }
        header("Location: " . $redirect_url); exit;
    }
}

// ==========================================
// [2] 검색 및 필터 쿼리 구성
// ==========================================
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

$stat_total = $db->query("SELECT COUNT(*) as cnt FROM monitor_conf")->fetch_assoc()['cnt'] ?? 0;
$stat_success = $db->query("SELECT COUNT(*) as cnt FROM monitor_conf WHERE `check` = 'alarm' AND value > 0")->fetch_assoc()['cnt'] ?? 0;
$stat_fail = $db->query("SELECT COUNT(*) as cnt FROM monitor_conf WHERE value = 0 AND `check` != 'disable'")->fetch_assoc()['cnt'] ?? 0;
$last_run = $db->query("SELECT MAX(mod_date) as last_run FROM monitor_conf")->fetch_assoc()['last_run'] ?? '-';
$next_run = $last_run !== '-' ? date('Y-m-d H:i:s', strtotime($last_run . ' + 1 minutes')) : '-';

$groups_res = $db->query("SELECT DISTINCT group_name FROM monitor_conf WHERE group_name != '' ORDER BY group_name ASC");
$existing_groups = [];
while($g = $groups_res->fetch_assoc()) { $existing_groups[] = $g['group_name']; }

$result = $db->query("SELECT * FROM monitor_conf WHERE $where_sql $order_query");

// ==========================================
// [3] View(화면) 호출
// ==========================================
require_once 'views/index.php';
?>