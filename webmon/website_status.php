<?php
// ==========================================
// [0] 환경 설정 및 공통 파일 로드
// ==========================================
require_once 'config/database.php';

function redirectCsvImportError(string $redirectUrl, string $detail): void {
    $separator = strpos($redirectUrl, '?') !== false ? '&' : '?';
    header("Location: " . $redirectUrl . $separator . "msg=csv_import_error&detail=" . rawurlencode($detail));
    exit;
}

function csvUnicodeLength(string $value): int {
    $matched = preg_match_all('/./us', $value);
    return $matched === false ? PHP_INT_MAX : $matched;
}
require_once 'config/language.php';
require_once 'classes/Utils.php';

// ==========================================
// [1] DB 처리 로직
// ==========================================
$query_string = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';

if (isset($_GET['action']) && $_GET['action'] == 'diagnose') {
    header('Content-Type: application/json; charset=utf-8');
    $no = (int)$_GET['no'];
    
    $res = $db->query("SELECT * FROM monitor_conf WHERE no = $no");
    if (!$res || !$row = $res->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => '대상 모니터링 정보를 찾을 수 없습니다.']);
        exit;
    }
    
    $url = $row['url'];
    $keyword = $row['contents'];
    $engine = $row['engine_type'] ?: 'lynx';
    $timeout = max(1, (int)($row['timeout_sec'] ?: 2));
    
    // 1. 웹사이트 진단 (HTTP Check)
    $ua = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) WebMon/2.1";
    $cmd_for = function ($target) use ($engine, $timeout, $ua) {
        if ($engine === 'curl') {
            return "timeout {$timeout} curl -k -s -L --compressed -A " . escapeshellarg($ua) . " --connect-timeout {$timeout} -w " . escapeshellarg("\n__WEBMON_HTTP__%{http_code}") . " " . escapeshellarg($target);
        }
        return "env FORCE_SSL_PROMPT=yes timeout {$timeout} lynx -source -mime_header -useragent=" . escapeshellarg($ua) . " -nolist -force_secure -connect_timeout={$timeout} " . escapeshellarg($target);
    };
    
    $cmd = $cmd_for($url);
    $t_start = microtime(true);
    $http_status = null;
    $target = $url;
    for ($hop = 0; $hop < 6; $hop++) {
        $raw = (string)shell_exec($cmd_for($target));
        $body = $raw;
        $http_status = null;
        if ($engine === 'curl') {
            $pos = strrpos($raw, '__WEBMON_HTTP__');
            if ($pos !== false) {
                $code = (int)substr($raw, $pos + 15);
                $http_status = $code > 0 ? $code : null;
                $body = substr($raw, 0, $pos);
            }
            break;
        }
        if (!preg_match('/^HTTP\/\S+\s+(\d{3})/', $raw, $hm)) break;
        $http_status = (int)$hm[1];
        $parts = preg_split('/\r?\n\r?\n/', $raw, 2);
        $body = $parts[1] ?? '';
        if (preg_match('/^content-encoding:\s*(gzip|x-gzip|deflate)/im', $raw, $em) && $body !== '') {
            $dec = @gzdecode($body);
            if ($dec === false) $dec = @gzinflate($body);
            if ($dec !== false) $body = $dec;
        }
        // lynx -mime_header 는 리다이렉트를 따라가지 않으므로 Location 을 직접 따라간다.
        if ($http_status >= 300 && $http_status < 400 && preg_match('/^location:\s*(\S+)/im', $raw, $lm)) {
            $loc = $lm[1];
            if (preg_match('#^https?://#i', $loc)) {
                $target = $loc;
            } elseif (preg_match('#^(https?://[^/]+)#i', $target, $base)) {
                $target = $base[1] . ($loc[0] === '/' ? $loc : '/' . $loc);
            } else {
                break;
            }
            continue;
        }
        break;
    }
    $elapsed = round(microtime(true) - $t_start, 3);
    $sql_http_status = $http_status === null ? 'NULL' : $http_status;

    $val = (mb_strpos($body, $keyword) !== false) ? 1 : 0;
    $reason = $val === 0 ? "[{$keyword}] 컨텐츠 감지 불가" : "";
    
    // 2. 도메인 만료 정보 갱신 (dom_mon = 1 인 경우 WHOIS 조회)
    $new_dom_expiry = $row['dom_expiry'];
    if ($row['dom_mon'] == 1) {
        // 도메인 호스트 파싱
        if (preg_match('/https?:\/\/([^\/:]+)/i', $url, $m)) {
            $host = $m[1];
            // 2단계 TLD 처리 및 도메인 정제
            $kr_tlds = ["go", "or", "re", "ac", "ne", "ms", "hs", "sc", "co", "pe",
                        "seoul", "busan", "daegu", "incheon", "gwangju", "daejeon",
                        "ulsan", "gyeonggi", "gangwon", "chungbuk", "chungnam",
                        "jeonbuk", "jeonnam", "gyeongbuk", "gyeongnam", "jeju"];
            $host = preg_replace('/^(www\d*|m)\./i', '', $host);
            $parts = explode('.', $host);
            $n = count($parts);
            if ($n >= 3 && $parts[$n-1] === 'kr' && in_array($parts[$n-2], $kr_tlds)) {
                $domain = implode('.', array_slice($parts, -3));
            } else {
                $domain = implode('.', array_slice($parts, -2));
            }
            
            // WHOIS 조회
            $whois_out = shell_exec("timeout 10 whois " . escapeshellarg($domain));
            if ($whois_out) {
                // 만료일 정규식 파싱
                if (preg_match('/(?:사용 종료일|Expiration Date|Registry Expiry Date|Expires on|Expiry date)\s*[:\s]*([\d.\-\s\/T]{10,})/i', $whois_out, $exp_m)) {
                    $raw_date = trim($exp_m[1]);
                    $raw_date = preg_replace('/[\s.]+/', '-', $raw_date);
                    $raw_date = rtrim($raw_date, '-');
                    $raw_date = preg_replace('/T.*$/', '', $raw_date);
                    $date_parsed = date('Y-m-d', strtotime(substr($raw_date, 0, 10)));
                    if ($date_parsed && $date_parsed !== '1970-01-01') {
                        $new_dom_expiry = $date_parsed;
                    }
                }
            }
        }
    }
    
    // 3. DB 업데이트
    $sql_val = $val;
    $sql_elapsed = (float)$elapsed;
    $sql_expiry_val = $new_dom_expiry ? "'$new_dom_expiry'" : "NULL";
    $safe_reason = $db->real_escape_string($reason);
    
    $db->query("UPDATE monitor_conf 
                SET `value` = $sql_val, 
                    error_msg = '$safe_reason',
                    http_status = $sql_http_status,
                    response_time = $sql_elapsed, 
                    dom_expiry = $sql_expiry_val, 
                    fail_since = IF($sql_val = 0, COALESCE(fail_since, NOW()), NULL),
                    mod_date = NOW(),
                    last_checked_at = NOW()
                WHERE no = $no");
                
    // monitor_log 기록
    $safe_url = $db->real_escape_string($url);
    $safe_cmd = $db->real_escape_string($cmd);
    $db->query("INSERT INTO monitor_log (monitor_conf_no, req_url, response_time, result, cmd) 
                VALUES ($no, '$safe_url', $sql_elapsed, $sql_val, '$safe_cmd')");
                
    echo json_encode([
        'success' => true,
        'value' => $val,
        'response_time' => $elapsed,
        'dom_expiry' => $new_dom_expiry,
        'reason' => $reason
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'rollback_job') {
    $job_id = (int)$_GET['job_id'];
    
    // 1. Fetch job details
    $job_res = $db->query("SELECT * FROM job_history WHERE id = $job_id AND is_rolled_back = 0");
    if ($job_res && $job = $job_res->fetch_assoc()) {
        $action_type = $job['action_type'];
        $snapshot = json_decode($job['snapshot'], true);
        
        if (is_array($snapshot)) {
            $db->query("SET FOREIGN_KEY_CHECKS = 0;");
            
            if ($action_type === 'bulk_update' || $action_type === 'edit') {
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
            } elseif ($action_type === 'csv_import') {
                foreach ($snapshot as $item) {
                    $no = (int)($item['no'] ?? 0);
                    if ($no > 0) {
                        $db->query("DELETE FROM monitor_conf WHERE no = $no");
                    }
                }
            } elseif ($action_type === 'settings_update') {
                require_once __DIR__ . '/classes/Utils.php';
                $before = $snapshot['before'] ?? [];
                if (!empty($before) && is_array($before)) {
                    $env_restore = [];
                    foreach ($before as $k => $v) {
                        if (str_starts_with($k, '_env_')) {
                            $env_key = strtoupper(substr($k, 5));
                            // 마스킹된 비밀번호는 복원 대상에서 제외 (기존 값 보존)
                            if ($env_key !== 'SMTP_PASS' || $v !== '••••••••') {
                                $env_restore[$env_key] = $v;
                            }
                        } else {
                            $safe_k = $db->real_escape_string($k);
                            $safe_v = $db->real_escape_string($v);
                            $db->query("INSERT INTO system_settings (key_name, key_value) VALUES ('$safe_k', '$safe_v') ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
                        }
                    }
                    if (!empty($env_restore)) {
                        Utils::writeEnv($env_restore);
                    }
                }
            }
            
            $db->query("SET FOREIGN_KEY_CHECKS = 1;");
            
            // AUTO_INCREMENT 카운터가 롤백 후 꼬이지 않도록 명시적으로 갱신
            $max_no_res = $db->query("SELECT MAX(no) as max_no FROM monitor_conf");
            $max_no = ($max_no_res && $row_m = $max_no_res->fetch_assoc()) ? (int)$row_m['max_no'] : 0;
            if ($max_no > 0) {
                $next_auto = $max_no + 1;
                $db->query("ALTER TABLE monitor_conf AUTO_INCREMENT = $next_auto");
            }
            
            // Mark job as rolled back
            $rolled_back_time = date('Y-m-d H:i:s');
            $db->query("UPDATE job_history SET is_rolled_back = 1, rolled_back_time = '$rolled_back_time' WHERE id = $job_id");
            
            header("Location: website_status.php?msg=rollback_success&open_history=1");
            exit;
        }
    }
    header("Location: website_status.php?msg=rollback_failed&open_history=1");
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

    if ($action === 'csv_import') {
        $upload = $_FILES['csv_file'] ?? null;
        if (!$upload || !isset($upload['error']) || $upload['error'] !== UPLOAD_ERR_OK) {
            redirectCsvImportError($redirect_url, 'CSV 파일 업로드에 실패했습니다.');
        }
        if (($upload['size'] ?? 0) > 5 * 1024 * 1024 || !is_file($upload['tmp_name'] ?? '') || filesize($upload['tmp_name']) > 5 * 1024 * 1024) {
            redirectCsvImportError($redirect_url, 'CSV 파일은 5MB 이하여야 합니다.');
        }

        $handle = fopen($upload['tmp_name'], 'rb');
        if ($handle === false) {
            redirectCsvImportError($redirect_url, 'CSV 파일을 읽을 수 없습니다.');
        }

        $header = fgetcsv($handle, 0, ',', '"', '');
        if (!$header) {
            fclose($handle);
            redirectCsvImportError($redirect_url, 'CSV 헤더를 찾을 수 없습니다.');
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$header[0]);
        $allowedFields = [
            'company', 'url', 'contents', 'group_name', 'check', 'ssl_mon', 'dom_mon',
            'comment', 'engine_type', 'retry_count', 'timeout_sec', 'email_mode',
            'sender_email', 'receiver_email'
        ];
        $headerMap = [];
        foreach ($header as $index => $column) {
            $key = strtolower(trim((string)$column));
            if (!in_array($key, $allowedFields, true)) {
                fclose($handle);
                redirectCsvImportError($redirect_url, "지원하지 않는 CSV 열: $column");
            }
            if (isset($headerMap[$key])) {
                fclose($handle);
                redirectCsvImportError($redirect_url, "중복된 CSV 열: $column");
            }
            $headerMap[$key] = $index;
        }
        foreach (['company', 'url', 'contents'] as $requiredField) {
            if (!isset($headerMap[$requiredField])) {
                fclose($handle);
                redirectCsvImportError($redirect_url, "필수 CSV 열이 없습니다: $requiredField");
            }
        }

        $validatedRows = [];
        $lineNumber = 1;
        $booleanValue = static function (string $value, int $default): ?int {
            if ($value === '') return $default;
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) return 1;
            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) return 0;
            return null;
        };
        while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $lineNumber++;
            if (count($values) === 1 && trim((string)$values[0]) === '') continue;
            if (count($values) !== count($header)) {
                fclose($handle);
                redirectCsvImportError($redirect_url, "행 $lineNumber: CSV 열 개수가 헤더와 일치하지 않습니다.");
            }
            if (count($validatedRows) >= 500) {
                fclose($handle);
                redirectCsvImportError($redirect_url, '한 번에 최대 500행까지 가져올 수 있습니다.');
            }
            $get = static function (string $field) use ($headerMap, $values): string {
                return isset($headerMap[$field]) ? trim((string)($values[$headerMap[$field]] ?? '')) : '';
            };
            $company = $get('company');
            $url = $get('url');
            $contents = $get('contents');
            $parsedUrl = filter_var($url, FILTER_VALIDATE_URL) ? parse_url($url) : false;
            if ($company === '' || $url === '' || $contents === '') {
                fclose($handle);
                redirectCsvImportError($redirect_url, "행 $lineNumber: 회사명, URL, 키워드가 필요합니다.");
            }
            if (csvUnicodeLength($company) > 200 || csvUnicodeLength($contents) > 500 || csvUnicodeLength($url) > 500) {
                fclose($handle);
                redirectCsvImportError($redirect_url, "행 $lineNumber: 회사명, URL 또는 키워드가 허용 길이를 초과합니다.");
            }
            if (!$parsedUrl || !isset($parsedUrl['scheme']) || !in_array(strtolower($parsedUrl['scheme']), ['http', 'https'], true)) {
                fclose($handle);
                redirectCsvImportError($redirect_url, "행 $lineNumber: 올바른 HTTP 또는 HTTPS URL이 아닙니다.");
            }

            $check = $get('check') ?: 'alarm';
            $checkAliases = ['active' => 'alarm', 'enabled' => 'alarm', 'mute' => 'no alarm', 'muted' => 'no alarm', 'disabled' => 'disable'];
            $check = $checkAliases[strtolower($check)] ?? strtolower($check);
            if (!in_array($check, ['alarm', 'no alarm', 'disable'], true)) {
                fclose($handle);
                redirectCsvImportError($redirect_url, "행 $lineNumber: check 값은 alarm, no alarm, disable 중 하나여야 합니다.");
            }

            $sslMon = $booleanValue($get('ssl_mon'), 1);
            $domMon = $booleanValue($get('dom_mon'), 1);
            if ($sslMon === null || $domMon === null) {
                fclose($handle);
                redirectCsvImportError($redirect_url, "행 $lineNumber: ssl_mon과 dom_mon은 1 또는 0이어야 합니다.");
            }
            $engine = strtolower($get('engine_type') ?: 'lynx');
            if (!in_array($engine, ['lynx', 'curl'], true)) {
                fclose($handle);
                redirectCsvImportError($redirect_url, "행 $lineNumber: engine_type은 lynx 또는 curl이어야 합니다.");
            }
            $retryCount = $get('retry_count') === '' ? 1 : (int)$get('retry_count');
            $timeoutSec = $get('timeout_sec') === '' ? 2 : (int)$get('timeout_sec');
            if (!in_array($retryCount, [1, 2, 3, 5], true) || !preg_match('/^\d+$/', $get('retry_count') === '' ? '1' : $get('retry_count'))) {
                fclose($handle);
                redirectCsvImportError($redirect_url, "행 $lineNumber: retry_count는 1, 2, 3, 5 중 하나여야 합니다.");
            }
            if ($timeoutSec < 1 || $timeoutSec > 30 || !preg_match('/^\d+$/', $get('timeout_sec') === '' ? '2' : $get('timeout_sec'))) {
                fclose($handle);
                redirectCsvImportError($redirect_url, "행 $lineNumber: timeout_sec는 1에서 30 사이여야 합니다.");
            }

            $emailMode = strtolower($get('email_mode') ?: 'none');
            if (!in_array($emailMode, ['none', 'postfix', 'smtp'], true)) {
                fclose($handle);
                redirectCsvImportError($redirect_url, "행 $lineNumber: email_mode는 none, postfix, smtp 중 하나여야 합니다.");
            }
            $senderEmail = $get('sender_email');
            $receiverEmail = $get('receiver_email');
            foreach (['발송자 이메일' => $senderEmail, '수신자 이메일' => $receiverEmail] as $label => $email) {
                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    fclose($handle);
                    redirectCsvImportError($redirect_url, "행 $lineNumber: $label 형식이 올바르지 않습니다.");
                }
            }
            $groupName = $get('group_name') ?: '기본 그룹';
            $comment = $get('comment');
            if (csvUnicodeLength($groupName) > 100 || csvUnicodeLength($comment) > 500) {
                fclose($handle);
                redirectCsvImportError($redirect_url, "행 $lineNumber: 그룹명 또는 메모가 허용 길이를 초과합니다.");
            }
            $validatedRows[] = compact('company', 'groupName', 'url', 'contents', 'check', 'sslMon', 'domMon', 'comment', 'senderEmail', 'receiverEmail', 'emailMode', 'engine', 'retryCount', 'timeoutSec');
        }
        fclose($handle);
        if (!$validatedRows) {
            redirectCsvImportError($redirect_url, '가져올 데이터 행이 없습니다.');
        }

        $addedCount = 0;
        $skippedCount = 0;
        $importedItems = [];
        try {
            $db->begin_transaction();
            $duplicateStmt = $db->prepare("SELECT no FROM monitor_conf WHERE url = ? LIMIT 1");
            $insertStmt = $db->prepare("INSERT INTO monitor_conf
                (company, group_name, url, contents, `check`, ssl_mon, dom_mon, comment, sender_email, receiver_email, email_mode, engine_type, retry_count, timeout_sec, mod_date, updated_at, value, response_time)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 1, 0)");
            $seenUrls = [];
            foreach ($validatedRows as $row) {
                if (isset($seenUrls[$row['url']])) {
                    $skippedCount++;
                    continue;
                }
                $seenUrls[$row['url']] = true;
                $duplicateStmt->bind_param('s', $row['url']);
                $duplicateStmt->execute();
                if ($duplicateStmt->get_result()->num_rows > 0) {
                    $skippedCount++;
                    continue;
                }
                $insertStmt->bind_param(
                    'sssssiisssssii',
                    $row['company'], $row['groupName'], $row['url'], $row['contents'], $row['check'],
                    $row['sslMon'], $row['domMon'], $row['comment'], $row['senderEmail'], $row['receiverEmail'],
                    $row['emailMode'], $row['engine'], $row['retryCount'], $row['timeoutSec']
                );
                $insertStmt->execute();
                $importedItems[] = ['no' => $db->insert_id, 'company' => $row['company'], 'url' => $row['url']];
                $addedCount++;
            }
            $duplicateStmt->close();
            $insertStmt->close();
            if ($importedItems) {
                $snapshot = json_encode($importedItems, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $actionType = 'csv_import';
                $changedFields = 'CSV import';
                $historyStmt = $db->prepare("INSERT INTO job_history (action_type, target_count, changed_fields, snapshot) VALUES (?, ?, ?, ?)");
                $historyStmt->bind_param('siss', $actionType, $addedCount, $changedFields, $snapshot);
                $historyStmt->execute();
                $historyStmt->close();
            }
            $db->commit();
        } catch (mysqli_sql_exception | JsonException $error) {
            $db->rollback();
            error_log('CSV monitor import failed: ' . $error->getMessage());
            redirectCsvImportError($redirect_url, '데이터베이스 등록에 실패하여 전체 작업을 취소했습니다.');
        }

        $separator = strpos($redirect_url, '?') !== false ? '&' : '?';
        header("Location: " . $redirect_url . $separator . "msg=csv_import_success&added=$addedCount&skipped=$skippedCount");
        exit;
    }
    elseif ($action === 'add' || $action === 'edit') {
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
            $sql = "INSERT INTO monitor_conf (company, group_name, url, contents, `check`, ssl_mon, dom_mon, comment, sender_email, receiver_email, email_mode, mod_date, updated_at, value, response_time, retry_count, timeout_sec, engine_type, smtp_host, smtp_port, smtp_user, smtp_pass, smtp_secure)
                    VALUES ('$company', '$group_name', '$url', '$contents', '$check', $ssl_mon, $dom_mon, '$comment', '$sender_email', '$receiver_email', '$email_mode', '$mod_date', '$mod_date', 1, 0.0, $retry_count, $timeout_sec, '$engine_type', '$smtp_host', $smtp_port, '$smtp_user', '$smtp_pass', '$smtp_secure')";
        } else {
            $no = (int)$_POST['no'];
            
            // Capture snapshot for rollback
            $res = $db->query("SELECT * FROM monitor_conf WHERE no = $no");
            if ($res && $row = $res->fetch_assoc()) {
                $snapshot_json = json_encode([$row], JSON_UNESCAPED_UNICODE);
                $safe_snapshot = $db->real_escape_string($snapshot_json);
                $db->query("INSERT INTO job_history (action_type, target_count, changed_fields, snapshot) 
                            VALUES ('edit', 1, 'individual_edit', '$safe_snapshot')");
            }
            
            $sql = "UPDATE monitor_conf SET 
                    company='$company', group_name='$group_name', url='$url', contents='$contents', `check`='$check', 
                    ssl_mon=$ssl_mon, dom_mon=$dom_mon, comment='$comment', sender_email='$sender_email', receiver_email='$receiver_email', email_mode='$email_mode', mod_date='$mod_date', updated_at='$mod_date',
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

        if (isset($_POST['chk_schedule'])) {
            $schedule_enabled = $_POST['sched_enabled'] ?? '';
            if (!in_array($schedule_enabled, ['0', '1'], true)) {
                header("Location: " . $redirect_url . (strpos($redirect_url, '?') !== false ? '&' : '?') . 'msg=invalid_schedule');
                exit;
            }

            if ($schedule_enabled === '1') {
                $mute_until_input = trim($_POST['mute_until'] ?? '');
                if ($mute_until_input === '') {
                    $mute_until_date = new DateTime();
                    $mute_until_date->modify('+2 hours');
                    $mute_until_input = $mute_until_date->format('Y-m-d\TH:i');
                }
                $mute_until_date = DateTime::createFromFormat('Y-m-d\TH:i', $mute_until_input);
                $date_errors = DateTime::getLastErrors();
                if (!$mute_until_date || ($date_errors && ($date_errors['warning_count'] || $date_errors['error_count'])) ||
                    $mute_until_date->format('Y-m-d\TH:i') !== $mute_until_input || $mute_until_date <= new DateTime()) {
                    header("Location: " . $redirect_url . (strpos($redirect_url, '?') !== false ? '&' : '?') . 'msg=invalid_schedule');
                    exit;
                }
                $mute_until_sql = "'" . $db->real_escape_string($mute_until_date->format('Y-m-d H:i:s')) . "'";
            } else {
                $mute_until_sql = 'NULL';
            }

            $updates[] = "sched_enabled = " . (int)$schedule_enabled;
            $updates[] = "mute_until = $mute_until_sql";
            $changed_fields_list[] = 'mute_schedule';
        }
        
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
            $updates[] = "updated_at = '$mod_date'";
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

require __DIR__ . '/config/dashboard.php';
require_once __DIR__ . '/classes/LogModel.php';

$groups_res = $db->query("SELECT DISTINCT group_name FROM monitor_conf WHERE group_name != '' ORDER BY group_name ASC");
$existing_groups = [];
while($g = $groups_res->fetch_assoc()) { $existing_groups[] = $g['group_name']; }

$log_rows = [];
$log_has_more = false;
$next_log_cursor = 0;
if (($current_view ?? 'card') === 'log') {
    $log_data = LogModel::fetchLogs($db, $_GET, $settings);
    $log_rows = $log_data['rows'];
    $log_has_more = $log_data['has_more'];
    $next_log_cursor = $log_data['next_cursor'];
}


// ==========================================
// [3] View(화면) 호출
// ==========================================
require_once __DIR__ . '/views/index.php';
?>