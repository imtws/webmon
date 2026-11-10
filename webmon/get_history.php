<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/language.php';

// 최근 50개의 작업 이력을 가져옵니다.
$history_res = $db->query("SELECT * FROM job_history ORDER BY id DESC LIMIT 50");
$history_list = [];
if ($history_res) {
    while ($row = $history_res->fetch_assoc()) {
        $history_list[] = $row;
    }
}

// 전체 모니터링 최신 상태를 한 번에 조회하여 메모리에 매핑 (N+1 쿼리 방지)
$all_conf = [];
$cur_res = $db->query("SELECT * FROM monitor_conf");
if ($cur_res) {
    while ($r = $cur_res->fetch_assoc()) {
        $all_conf[(int)$r['no']] = $r;
    }
}

// 헬퍼 함수: 필드 포맷팅
if (!function_exists('formatHistoryValue')) {
    function formatHistoryValue($key, $val, $isKo) {
        if ($val === null || $val === '') return '-';
        if ($key === 'check') {
            if ($val === 'alarm') return $isKo ? '활성화' : 'Active';
            if ($val === 'no alarm') return $isKo ? '무음' : 'Muted';
            if ($val === 'disable') return $isKo ? '비활성화' : 'Disabled';
        }
        if ($key === 'email_mode') {
            if ($val === 'none') return $isKo ? '발송 안함' : 'None';
            if ($val === 'direct' || $val === 'postfix') return $isKo ? '수신 서버로 직접 발송' : 'Direct delivery';
            if ($val === 'smtp') return $isKo ? '개별 SMTP 발송' : 'Individual SMTP';
        }
        if ($key === 'ssl_mon' || $key === 'dom_mon') {
            if ($val == 1) return $isKo ? '모니터링 활성' : 'Active';
            return $isKo ? '비활성' : 'Disabled';
        }
        if ($key === 'engine_type') {
            return strtoupper($val);
        }
        if ($key === 'smtp_pass') {
            return $val !== '' ? '••••••••' : '-';
        }
        return htmlspecialchars($val);
    }
}

// 헬퍼 함수: 비교 렌더링
if (!function_exists('renderFieldDiff')) {
    function renderFieldDiff($key, $label, $beforeVal, $afterVal, $isKo) {
        $fmtBefore = formatHistoryValue($key, $beforeVal, $isKo);
        $fmtAfter = formatHistoryValue($key, $afterVal, $isKo);
        
        if ($beforeVal !== $afterVal) {
            return "
            <div class='flex flex-col gap-1.5 p-2.5 rounded-xl bg-amber-500/15 dark:bg-amber-500/5 border border-amber-500/40 dark:border-amber-500/30 shadow-xs transition-all duration-200 hover:scale-[1.02]'>
                <span class='text-[10px] text-amber-600 dark:text-amber-400 font-black tracking-wide uppercase flex items-center gap-1'>
                    <i class='fas fa-exclamation-circle text-[9px]'></i>$label
                </span>
                <div class='flex items-center gap-1.5 flex-wrap text-[11px] mt-0.5'>
                    <span class='text-red-500 line-through font-semibold bg-red-50 dark:bg-red-950/20 px-1 py-0.5 rounded'>$fmtBefore</span>
                    <i class='fas fa-arrow-right text-slate-400 text-[9px]'></i>
                    <span class='text-green-600 dark:text-green-400 font-black bg-green-50 dark:bg-green-950/20 px-1 py-0.5 rounded'>$fmtAfter</span>
                </div>
            </div>";
        } else {
            return "
            <div class='flex flex-col gap-1 p-2.5 rounded-xl bg-slate-100/40 dark:bg-slate-800/20 border border-slate-200/30 dark:border-slate-800/20 opacity-55 hover:opacity-100 transition-opacity duration-200'>
                <span class='text-[10px] text-[var(--text-sub)] font-semibold'>$label</span>
                <span class='font-bold text-[var(--text-main)] text-[11px] mt-0.5'>$fmtBefore</span>
            </div>";
        }
    }
}

// 헬퍼 함수: 단일 렌더링 (삭제된 경우 등)
if (!function_exists('renderFieldStatic')) {
    function renderFieldStatic($key, $label, $val, $isKo) {
        $fmtVal = formatHistoryValue($key, $val, $isKo);
        return "
        <div class='flex flex-col gap-1 p-2.5 rounded-xl bg-slate-100/40 dark:bg-slate-800/20 border border-slate-200/30 dark:border-slate-800/20 opacity-75 hover:opacity-100 transition-opacity duration-200'>
            <span class='text-[10px] text-[var(--text-sub)] font-semibold'>$label</span>
            <span class='font-bold text-[var(--text-main)] text-[11px] mt-0.5'>$fmtVal</span>
        </div>";
    }
}
?>

<?php if (empty($history_list)): ?>
    <div class="flex flex-col items-center justify-center py-16 text-center">
        <div class="w-16 h-16 bg-slate-100 dark:bg-slate-800/50 rounded-full flex items-center justify-center text-slate-400 dark:text-slate-600 mb-4">
            <i class="fas fa-folder-open text-2xl"></i>
        </div>
        <p class="text-sm font-bold text-[var(--text-sub)]">
            <?= $current_lang == 'KO' ? '기록된 작업 이력이 없습니다.' : 'No job history recorded yet.' ?>
        </p>
    </div>
<?php else: ?>
    <div class="space-y-4">
        <p class="text-xs text-[var(--text-sub)] mb-2">
            <?= $current_lang == 'KO' 
                ? '※ 최근 50개의 이력이 표시됩니다. 원복 시 삭제되거나 수정된 설정이 이전 스냅샷 상태로 즉시 복구됩니다.' 
                : '※ Shows the last 50 changes. Rollback instantly restores settings to their exact previous state.' ?>
        </p>
        
        <div class="overflow-x-auto border border-[var(--border-color)] rounded-xl bg-[var(--bg-color)]">
            <table class="history-table w-full min-w-[760px] text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-800/60 border-b border-[var(--border-color)] text-[var(--text-sub)] font-extrabold">
                        <th class="p-4 w-[60px] text-center">ID</th>
                        <th class="p-4 w-[130px]"><?= $current_lang == 'KO' ? '실행 시각' : 'Time' ?></th>
                        <th class="p-4 w-[95px]"><?= $current_lang == 'KO' ? '작업 유형' : 'Action' ?></th>
                        <th class="p-4 w-[65px] text-center"><?= $current_lang == 'KO' ? '대상수' : 'Targets' ?></th>
                        <th class="p-4"><?= $current_lang == 'KO' ? '대상 설정 변경 내역 요약' : 'Targets Summary' ?></th>
                        <th class="p-4 w-[185px] text-center"><?= $current_lang == 'KO' ? '관리' : 'Manage' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history_list as $row): 
                        $action_type = $row['action_type'];
                        $target_count = $row['target_count'];
                        $changed_fields = $row['changed_fields'];
                        $is_rolled = $row['is_rolled_back'];
                        
                        // Action Badge styling & translation
                        $badge_class = '';
                        $action_name = '';
                        if ($action_type === 'bulk_update') {
                            $badge_class = 'bg-blue-50 text-blue-600 border border-blue-200 dark:bg-blue-900/20 dark:text-blue-400 dark:border-blue-800/40';
                            $action_name = $current_lang == 'KO' ? '일괄 수정' : 'Bulk Edit';
                        } elseif ($action_type === 'bulk_delete') {
                            $badge_class = 'bg-red-50 text-red-600 border border-red-200 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800/40';
                            $action_name = $current_lang == 'KO' ? '일괄 삭제' : 'Bulk Del';
                        } elseif ($action_type === 'delete') {
                            $badge_class = 'bg-orange-50 text-orange-600 border border-orange-200 dark:bg-orange-900/20 dark:text-orange-400 dark:border-orange-800/40';
                            $action_name = $current_lang == 'KO' ? '삭제' : 'Delete';
                        } elseif ($action_type === 'edit') {
                            $badge_class = 'bg-green-50 text-green-600 border border-green-200 dark:bg-green-900/20 dark:text-green-400 dark:border-green-800/40';
                            $action_name = $current_lang == 'KO' ? '수정' : 'Edit';
                        } elseif ($action_type === 'csv_import') {
                            $badge_class = 'bg-sky-50 text-sky-700 border border-sky-200 dark:bg-sky-900/20 dark:text-sky-300 dark:border-sky-800/40';
                            $action_name = $current_lang == 'KO' ? 'CSV 가져오기' : 'CSV Import';
                        } elseif ($action_type === 'settings_update') {
                            $badge_class = 'bg-purple-50 text-purple-700 border border-purple-200 dark:bg-purple-900/20 dark:text-purple-300 dark:border-purple-800/40';
                            $action_name = $current_lang == 'KO' ? '전역 설정' : 'Settings';
                        } else {
                            $badge_class = 'bg-gray-50 text-gray-600 border border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700';
                            $action_name = $action_type;
                        }
                        
                        // Parse changed fields to Korean / Readable format
                        $fields_formatted = '';
                        if ($action_type === 'settings_update' && !empty($changed_fields)) {
                            $key_labels_ko = [
                                'tg_enabled' => '텔레그램 활성화', 'email_enabled' => '이메일 활성화',
                                'smtp_mode' => 'SMTP 모드', 'alert_email' => '알림 수신 이메일',
                                'global_sender_email' => '전역 발송자', 'retention_days' => '로그 보존(일)',
                                'def_email_mode' => '기본 발송 방식', 'def_check' => '기본 알람 상태',
                                'def_engine_type' => '기본 엔진', 'def_timeout_sec' => '기본 타임아웃',
                                'def_retry_count' => '기본 재시도', 'def_mute_hours' => '기본 무음(시간)',
                                'def_ssl_mon' => 'SSL 모니터링', 'def_dom_mon' => '도메인 모니터링',
                                'alert_concurrency' => '동시 실행 상한',
                                'report_enabled' => '리포트 활성화', 'report_time' => '리포트 발송 시각',
                                'report_weekdays' => '리포트 요일', 'report_within_days' => '만료 경고 기간',
                                'report_receiver_email' => '리포트 수신 이메일',
                                '_env_tg_token' => 'TG 토큰', '_env_tg_chat_id' => 'TG Chat ID',
                                '_env_smtp_host' => 'SMTP 호스트', '_env_smtp_port' => 'SMTP 포트',
                                '_env_smtp_user' => 'SMTP 계정', '_env_smtp_pass' => 'SMTP 비밀번호',
                                '_env_smtp_secure' => 'SMTP 보안', '_env_webmon_url' => 'WebMon URL',
                            ];
                            $readable = [];
                            foreach (array_map('trim', explode(',', $changed_fields)) as $ck) {
                                $readable[] = $key_labels_ko[$ck] ?? $ck;
                            }
                            $fields_formatted = implode(', ', $readable);
                        } elseif (($action_type === 'bulk_update' || $action_type === 'edit') && !empty($changed_fields)) {
                            if ($changed_fields === 'individual_edit') {
                                $fields_formatted = $current_lang == 'KO' ? '개별 수정' : 'Individual Settings';
                            } else {
                                $fields_arr = array_map('trim', explode(',', $changed_fields));
                                $formatted_arr = [];
                                foreach ($fields_arr as $f) {
                                    if ($f === 'group_name') $formatted_arr[] = $current_lang == 'KO' ? '그룹' : 'Group';
                                    elseif ($f === 'check') $formatted_arr[] = $current_lang == 'KO' ? '상태' : 'Status';
                                    elseif ($f === 'engine_type') $formatted_arr[] = $current_lang == 'KO' ? '엔진' : 'Engine';
                                    elseif ($f === 'retry_count, timeout_sec' || $f === 'retry_count' || $f === 'timeout_sec') $formatted_arr[] = $current_lang == 'KO' ? '체크수/타임아웃' : 'Retry/Timeout';
                                    elseif ($f === 'ssl_mon, dom_mon' || $f === 'ssl_mon' || $f === 'dom_mon') $formatted_arr[] = $current_lang == 'KO' ? '만료일 모니터링' : 'Expiry Mon';
                                    elseif ($f === 'email_notification') $formatted_arr[] = $current_lang == 'KO' ? '이메일 알림' : 'Email Alert';
                                    elseif ($f === 'mute_schedule') $formatted_arr[] = $current_lang == 'KO' ? '일시 무음 스케줄' : 'Mute Schedule';
                                    elseif ($f === 'smtp_server_settings') $formatted_arr[] = $current_lang == 'KO' ? '개별 SMTP' : 'SMTP Config';
                                    else $formatted_arr[] = $f;
                                }
                                $fields_formatted = implode(', ', $formatted_arr);
                            }
                        }
                        
                        // Extract companies from snapshot
                        $snapshot = json_decode($row['snapshot'], true);
                        $companies = [];
                        $is_settings_snap = ($action_type === 'settings_update');
                        if (!$is_settings_snap && is_array($snapshot)) {
                            foreach ($snapshot as $item) {
                                if (isset($item['company'])) {
                                    $companies[] = $item['company'];
                                }
                            }
                        }
                        $companies_str = $is_settings_snap
                            ? ($current_lang == 'KO' ? '전역 모니터링 설정' : 'Global Settings')
                            : implode(', ', $companies);
                        $companies_truncated = mb_strlen($companies_str) > 40 ? mb_substr($companies_str, 0, 40) . '...' : $companies_str;
                    ?>
                        <tr class="border-b border-[var(--border-color)] hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-colors">
                            <td class="p-4 font-bold text-center text-[var(--text-sub)]"><?= $row['id'] ?></td>
                            <td class="p-4 text-[var(--text-sub)]"><?= date('Y-m-d H:i:s', strtotime($row['job_time'])) ?></td>
                            <td class="p-4">
                                <span class="inline-block px-2.5 py-0.5 rounded text-[10px] font-extrabold uppercase <?= $badge_class ?>">
                                    <?= $action_name ?>
                                </span>
                            </td>
                            <td class="p-4 font-bold text-center"><?= $target_count ?></td>
                            <td class="p-4">
                                <div class="flex flex-col gap-0.5">
                                    <span class="font-bold text-[var(--text-main)]"><?= htmlspecialchars($companies_truncated) ?></span>
                                    <?php if (!empty($fields_formatted)): ?>
                                        <span class="text-[10px] text-blue-500 dark:text-blue-400 font-medium">
                                            <i class="fas fa-edit mr-1"></i><?= $current_lang == 'KO' ? '변경 항목' : 'Modified' ?>: <?= htmlspecialchars($fields_formatted) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                <div class="flex items-center justify-center gap-1.5 flex-nowrap">
                                    <button type="button" onclick="toggleJobDetail(<?= $row['id'] ?>)" class="whitespace-nowrap px-2.5 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-[var(--text-sub)] hover:text-[var(--text-main)] rounded-lg transition-colors text-[10px] font-bold flex items-center gap-1">
                                        <i class="fas fa-chevron-down text-[8px] transition-transform" id="detail-arrow-<?= $row['id'] ?>"></i><span><?= $current_lang == 'KO' ? '상세 비교' : 'Details' ?></span>
                                    </button>
                                    <?php if ($is_rolled): ?>
                                        <span class="whitespace-nowrap text-[10px] text-green-500 font-bold border border-green-200 bg-green-50/50 dark:border-green-800/30 dark:bg-green-950/20 px-2.5 py-1.5 rounded-lg cursor-not-allowed select-none flex items-center gap-1" title="<?= $row['rolled_back_time'] ?>">
                                            <i class="fas fa-undo text-[8px]"></i><span><?= $current_lang == 'KO' ? '원복됨' : 'Rolled Back' ?></span>
                                        </span>
                                    <?php else: ?>
                                        <button type="button" onclick="confirmRollback(<?= $row['id'] ?>, <?= $target_count ?>, '<?= $action_type ?>')" class="whitespace-nowrap px-2.5 py-1.5 bg-amber-500 hover:bg-amber-600 text-white rounded-lg transition-colors text-[10px] font-black shadow-sm flex items-center gap-1">
                                            <i class="fas fa-history text-[8px]"></i><span><?= $current_lang == 'KO' ? '원복' : 'Rollback' ?></span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Toggleable Details Panel (Before -> After Comparison) -->
                        <tr id="job-detail-<?= $row['id'] ?>" class="hidden bg-slate-50/70 dark:bg-slate-800/20 border-b border-[var(--border-color)]">
                            <td colspan="6" class="p-6 pl-12 pr-6">
                                <div class="space-y-4">
                                    <div class="flex justify-between items-center border-b border-[var(--border-color)] pb-2">
                                        <h4 class="font-extrabold text-[var(--text-main)] text-xs flex items-center gap-1.5">
                                            <i class="fas fa-exchange-alt text-[#18aee7]"></i>
                                            <span><?= $current_lang == 'KO' ? '설정 상태 비교 내역' : 'Configuration Diff Settings' ?></span>
                                        </h4>
                                        <span class="text-[10px] bg-amber-500/10 text-amber-600 dark:text-amber-400 px-2 py-0.5 rounded font-bold">
                                            <?= $current_lang == 'KO' ? '주황색: 변경됨 (이전 ➔ 현재)' : 'Orange: Changed (Before ➔ Current)' ?>
                                        </span>
                                    </div>
                                    
                                    <div class="space-y-4 max-h-[350px] overflow-y-auto style-scrollbar pr-2">
                                        <?php 
                                        if ($action_type === 'settings_update' && is_array($snapshot)):
                                            $before_data = $snapshot['before'] ?? [];
                                            $after_data = $snapshot['after'] ?? [];
                                            $settings_labels = [
                                                'tg_enabled' => $current_lang == 'KO' ? '텔레그램 알림' : 'Telegram Alerts',
                                                '_env_tg_token' => $current_lang == 'KO' ? 'TG 봇 토큰' : 'TG Bot Token',
                                                '_env_tg_chat_id' => $current_lang == 'KO' ? 'TG Chat ID' : 'TG Chat ID',
                                                'email_enabled' => $current_lang == 'KO' ? '이메일 알림' : 'Email Alerts',
                                                'smtp_mode' => $current_lang == 'KO' ? 'SMTP 발송 모드' : 'SMTP Mode',
                                                '_env_smtp_host' => $current_lang == 'KO' ? 'SMTP 호스트' : 'SMTP Host',
                                                '_env_smtp_port' => $current_lang == 'KO' ? 'SMTP 포트' : 'SMTP Port',
                                                '_env_smtp_user' => $current_lang == 'KO' ? 'SMTP 계정' : 'SMTP User',
                                                '_env_smtp_pass' => $current_lang == 'KO' ? 'SMTP 비밀번호' : 'SMTP Pass',
                                                '_env_smtp_secure' => $current_lang == 'KO' ? 'SMTP 보안' : 'SMTP Secure',
                                                'alert_email' => $current_lang == 'KO' ? '알림 수신 이메일' : 'Alert Email',
                                                '_env_webmon_url' => $current_lang == 'KO' ? 'WebMon URL' : 'WebMon URL',
                                                'retention_days' => $current_lang == 'KO' ? '로그 보존(일)' : 'Retention Days',
                                                'global_sender_email' => $current_lang == 'KO' ? '전역 발송자 이메일' : 'Global Sender Email',
                                                'def_email_mode' => $current_lang == 'KO' ? '기본 발송 방식' : 'Default Email Mode',
                                                'def_sender_email' => $current_lang == 'KO' ? '기본 발송자' : 'Default Sender',
                                                'def_receiver_email' => $current_lang == 'KO' ? '기본 수신자' : 'Default Receiver',
                                                'def_check' => $current_lang == 'KO' ? '기본 알람 상태' : 'Default Status',
                                                'def_ssl_mon' => $current_lang == 'KO' ? '기본 SSL 모니터링' : 'Default SSL Mon',
                                                'def_dom_mon' => $current_lang == 'KO' ? '기본 도메인 모니터링' : 'Default Domain Mon',
                                                'def_engine_type' => $current_lang == 'KO' ? '기본 실행 엔진' : 'Default Engine',
                                                'def_retry_count' => $current_lang == 'KO' ? '기본 재시도 횟수' : 'Default Retries',
                                                'def_timeout_sec' => $current_lang == 'KO' ? '기본 타임아웃(초)' : 'Default Timeout',
                                                'def_mute_hours' => $current_lang == 'KO' ? '기본 무음(시간)' : 'Default Mute Hours',
                                                'log_max_lines' => $current_lang == 'KO' ? '최대 로그 라인 수' : 'Max Log Lines',
                                                'log_rotate_mb' => $current_lang == 'KO' ? '로그 로테이트 크기' : 'Log Rotate MB',
                                                'alert_concurrency' => $current_lang == 'KO' ? '동시 실행 상한' : 'Concurrency Limit',
                                                'alert_format' => $current_lang == 'KO' ? '알림 템플릿 형식' : 'Alert Format',
                                                'email_subject' => $current_lang == 'KO' ? '이메일 알림 제목' : 'Email Subject',
                                                'report_enabled' => $current_lang == 'KO' ? '정기 리포트 활성화' : 'Report Enabled',
                                                'report_time' => $current_lang == 'KO' ? '리포트 발송 시각' : 'Report Time',
                                                'report_weekdays' => $current_lang == 'KO' ? '리포트 발송 요일' : 'Report Weekdays',
                                                'report_within_days' => $current_lang == 'KO' ? '만료 경고 기간(일)' : 'Report Expiry Days',
                                                'report_receiver_email' => $current_lang == 'KO' ? '리포트 수신 이메일' : 'Report Receiver Email',
                                            ];
                                            $changed_htmls = [];
                                            $unchanged_htmls = [];
                                            foreach ($settings_labels as $key => $label) {
                                                $b_val = $before_data[$key] ?? '';
                                                $a_val = $after_data[$key] ?? '';
                                                $html = renderFieldDiff($key, $label, $b_val, $a_val, $current_lang === 'KO');
                                                if ($b_val !== $a_val) {
                                                    $changed_htmls[] = $html;
                                                } else {
                                                    $unchanged_htmls[] = $html;
                                                }
                                            }
                                        ?>
                                            <div class="p-4 rounded-xl border border-[var(--border-color)] bg-[var(--card-bg)] shadow-xs flex flex-col gap-3">
                                                <div class="flex justify-between items-start gap-4">
                                                    <span class="font-black text-[var(--text-main)] text-[13px]">
                                                        <?= $current_lang == 'KO' ? '모니터링 프로그램 환경 설정 변경 내역' : 'System Configuration Changes' ?>
                                                    </span>
                                                </div>
                                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2.5">
                                                    <?= implode('', $changed_htmls) . implode('', $unchanged_htmls) ?>
                                                </div>
                                            </div>
                                        <?php
                                        elseif (is_array($snapshot)): 
                                            foreach ($snapshot as $item): 
                                                $no = (int)($item['no'] ?? 0);
                                                
                                                // 사전 로딩된 맵에서 최신 값 조회 (초고속 O(1))
                                                $current_item = $all_conf[$no] ?? null;
                                                $is_deleted_now = !$current_item;
                                        ?>
                                            <div class="p-4 rounded-xl border border-[var(--border-color)] bg-[var(--card-bg)] shadow-xs flex flex-col gap-3">
                                                <!-- Item Header -->
                                                <div class="flex justify-between items-start gap-4">
                                                    <div class="flex flex-col gap-0.5">
                                                        <span class="font-black text-[var(--text-main)] text-[13px]">
                                                            <?= htmlspecialchars($item['company'] ?? '') ?>
                                                        </span>
                                                        <span class="text-blue-500 dark:text-blue-400 font-mono text-[11px] hover:underline">
                                                            <a href="<?= htmlspecialchars($item['url'] ?? '#') ?>" target="_blank"><?= htmlspecialchars($item['url'] ?? '') ?></a>
                                                        </span>
                                                    </div>
                                                    <?php if ($is_deleted_now): ?>
                                                        <span class="text-[9px] bg-red-100 text-red-800 dark:bg-red-950/30 dark:text-red-400 px-2 py-0.5 rounded font-bold flex items-center gap-1">
                                                            <i class="fas fa-trash-alt"></i><?= $current_lang == 'KO' ? '현재 삭제됨' : 'Deleted Now' ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Settings Grid -->
                                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2.5">
                                                    <?php 
                                                    $fields_to_render = [
                                                        'group_name' => $current_lang == 'KO' ? '그룹명' : 'Group',
                                                        'check' => $current_lang == 'KO' ? '알람 상태' : 'Alarm Status',
                                                        'engine_type' => $current_lang == 'KO' ? '실행 엔진' : 'Engine',
                                                        'timeout_sec' => $current_lang == 'KO' ? '타임아웃(초)' : 'Timeout (s)',
                                                        'retry_count' => $current_lang == 'KO' ? '재시도 횟수' : 'Retry Count',
                                                        'ssl_mon' => $current_lang == 'KO' ? 'SSL 모니터링' : 'SSL Monitor',
                                                        'dom_mon' => $current_lang == 'KO' ? '도메인 모니터링' : 'Domain Monitor',
                                                        'email_mode' => $current_lang == 'KO' ? '메일 발송 방식' : 'Email Mode',
                                                        'sender_email' => $current_lang == 'KO' ? '발송자 이메일' : 'Sender Email',
                                                        'receiver_email' => $current_lang == 'KO' ? '수신자 이메일' : 'Receiver Email',
                                                        'smtp_host' => $current_lang == 'KO' ? 'SMTP 호스트' : 'SMTP Host',
                                                        'smtp_port' => $current_lang == 'KO' ? 'SMTP 포트' : 'SMTP Port',
                                                        'smtp_secure' => $current_lang == 'KO' ? 'SMTP 보안' : 'SMTP Secure',
                                                    ];
                                                    
                                                    $changed_htmls = [];
                                                    $unchanged_htmls = [];
                                                    
                                                    foreach ($fields_to_render as $key => $label) {
                                                        $before_val = $item[$key] ?? null;
                                                        
                                                        if ($is_deleted_now) {
                                                            echo renderFieldStatic($key, $label, $before_val, $current_lang === 'KO');
                                                        } else {
                                                            $after_val = $current_item[$key] ?? null;
                                                            $html = renderFieldDiff($key, $label, $before_val, $after_val, $current_lang === 'KO');
                                                            if ($before_val != $after_val) {
                                                                $changed_htmls[] = $html;
                                                            } else {
                                                                $unchanged_htmls[] = $html;
                                                            }
                                                        }
                                                    }
                                                    
                                                    if (!$is_deleted_now) {
                                                        echo implode('', $changed_htmls) . implode('', $unchanged_htmls);
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        <?php 
                                            endforeach; 
                                        endif; 
                                        ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>