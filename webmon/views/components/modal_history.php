<?php
// ==========================================
// 작업 이력 및 원복 모달 (Job History & Rollback Modal)
// ==========================================

// 최근 50개의 작업 이력을 가져옵니다.
$history_res = $db->query("SELECT * FROM job_history ORDER BY id DESC LIMIT 50");
$history_list = [];
if ($history_res) {
    while ($row = $history_res->fetch_assoc()) {
        $history_list[] = $row;
    }
}
?>

<div id="historyModal" class="hidden fixed inset-0 z-[1000] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
    <div class="modal-content flex flex-col w-full max-w-[750px] bg-[var(--card-bg)] rounded-2xl shadow-2xl border border-[var(--border-color)] max-h-[85vh] overflow-hidden">
        
        <!-- Header -->
        <div class="px-8 py-5 border-b border-[var(--border-color)] flex justify-between items-center bg-slate-50 dark:bg-slate-800/50">
            <h2 class="text-lg font-black text-[var(--text-main)] flex items-center gap-2">
                <i class="fas fa-history text-amber-500 animate-pulse"></i>
                <span><?= $current_lang == 'KO' ? '작업 이력 및 원복 관리' : 'Job History & Rollback' ?></span>
            </h2>
            <button type="button" onclick="closeHistoryModal()" class="text-[var(--text-sub)] hover:text-red-500 transition-colors focus:outline-none">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        
        <!-- Content Body -->
        <div class="flex-1 overflow-y-auto p-8 style-scrollbar">
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
                            ? '※ 최근 50개의 일괄 수정, 일괄 삭제, 개별 삭제 이력이 표시됩니다. 원복 시 삭제되거나 변경된 설정이 이전 상태로 그대로 복구됩니다.' 
                            : '※ Shows the last 50 bulk updates, bulk deletes, and individual deletes. Rollback restores settings to their exact previous state.' ?>
                    </p>
                    
                    <div class="overflow-hidden border border-[var(--border-color)] rounded-xl bg-[var(--bg-color)]">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-slate-800/60 border-b border-[var(--border-color)] text-[var(--text-sub)] font-extrabold">
                                    <th class="p-3 w-[60px] text-center">ID</th>
                                    <th class="p-3 w-[130px]"><?= $current_lang == 'KO' ? '실행 시각' : 'Time' ?></th>
                                    <th class="p-3 w-[95px]"><?= $current_lang == 'KO' ? '작업 유형' : 'Action' ?></th>
                                    <th class="p-3 w-[65px] text-center"><?= $current_lang == 'KO' ? '대상수' : 'Targets' ?></th>
                                    <th class="p-3"><?= $current_lang == 'KO' ? '상세 내역' : 'Details' ?></th>
                                    <th class="p-3 w-[120px] text-center"><?= $current_lang == 'KO' ? '관리' : 'Manage' ?></th>
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
                                    } else {
                                        $badge_class = 'bg-gray-50 text-gray-600 border border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700';
                                        $action_name = $action_type;
                                    }
                                    
                                    // Parse changed fields to Korean / Readable format
                                    $fields_formatted = '';
                                    if ($action_type === 'bulk_update' && !empty($changed_fields)) {
                                        $fields_arr = array_map('trim', explode(',', $changed_fields));
                                        $formatted_arr = [];
                                        foreach ($fields_arr as $f) {
                                            if ($f === 'group_name') $formatted_arr[] = $current_lang == 'KO' ? '그룹' : 'Group';
                                            elseif ($f === 'check') $formatted_arr[] = $current_lang == 'KO' ? '상태' : 'Status';
                                            elseif ($f === 'engine_type') $formatted_arr[] = $current_lang == 'KO' ? '엔진' : 'Engine';
                                            elseif ($f === 'retry_count, timeout_sec' || $f === 'retry_count' || $f === 'timeout_sec') $formatted_arr[] = $current_lang == 'KO' ? '체크수/타임아웃' : 'Retry/Timeout';
                                            elseif ($f === 'ssl_mon, dom_mon' || $f === 'ssl_mon' || $f === 'dom_mon') $formatted_arr[] = $current_lang == 'KO' ? '만료일 모니터링' : 'Expiry Mon';
                                            elseif ($f === 'email_notification') $formatted_arr[] = $current_lang == 'KO' ? '이메일 알림' : 'Email Alert';
                                            elseif ($f === 'smtp_server_settings') $formatted_arr[] = $current_lang == 'KO' ? '개별 SMTP' : 'SMTP Config';
                                            else $formatted_arr[] = $f;
                                        }
                                        $fields_formatted = implode(', ', $formatted_arr);
                                    }
                                    
                                    // Extract companies from snapshot
                                    $snapshot = json_decode($row['snapshot'], true);
                                    $companies = [];
                                    if (is_array($snapshot)) {
                                        foreach ($snapshot as $item) {
                                            if (isset($item['company'])) {
                                                $companies[] = $item['company'];
                                            }
                                        }
                                    }
                                    $companies_str = implode(', ', $companies);
                                    $companies_truncated = mb_strlen($companies_str) > 25 ? mb_substr($companies_str, 0, 25) . '...' : $companies_str;
                                ?>
                                    <tr class="border-b border-[var(--border-color)] hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-colors">
                                        <td class="p-3 font-bold text-center text-[var(--text-sub)]"><?= $row['id'] ?></td>
                                        <td class="p-3 text-[var(--text-sub)]"><?= date('Y-m-d H:i:s', strtotime($row['job_time'])) ?></td>
                                        <td class="p-3">
                                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-extrabold uppercase <?= $badge_class ?>">
                                                <?= $action_name ?>
                                            </span>
                                        </td>
                                        <td class="p-3 font-bold text-center"><?= $target_count ?></td>
                                        <td class="p-3">
                                            <div class="flex flex-col gap-0.5">
                                                <span class="font-bold text-[var(--text-main)]"><?= htmlspecialchars($companies_truncated) ?></span>
                                                <?php if (!empty($fields_formatted)): ?>
                                                    <span class="text-[10px] text-blue-500 font-medium">
                                                        <i class="fas fa-edit mr-1"></i><?= $current_lang == 'KO' ? '변경 항목' : 'Modified' ?>: <?= htmlspecialchars($fields_formatted) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="p-3 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <button type="button" onclick="toggleJobDetail(<?= $row['id'] ?>)" class="px-2 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-[var(--text-sub)] hover:text-[var(--text-main)] rounded transition-colors text-[10px] font-bold">
                                                    <i class="fas fa-chevron-down mr-1 transition-transform" id="detail-arrow-<?= $row['id'] ?>"></i><?= $current_lang == 'KO' ? '상세' : 'Info' ?>
                                                </button>
                                                <?php if ($is_rolled): ?>
                                                    <span class="text-[10px] text-green-500 font-bold border border-green-200 bg-green-50/50 dark:border-green-800/30 dark:bg-green-950/20 px-2 py-1 rounded cursor-not-allowed select-none" title="<?= $row['rolled_back_time'] ?>">
                                                        <i class="fas fa-undo mr-1"></i><?= $current_lang == 'KO' ? '원복됨' : 'Rolled Back' ?>
                                                    </span>
                                                <?php else: ?>
                                                    <button type="button" onclick="confirmRollback(<?= $row['id'] ?>, <?= $target_count ?>, '<?= $action_type ?>')" class="px-2.5 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded transition-colors text-[10px] font-black shadow-sm flex items-center gap-1">
                                                        <i class="fas fa-history text-[8px]"></i><?= $current_lang == 'KO' ? '원복' : 'Rollback' ?>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <!-- Toggleable Details Panel -->
                                    <tr id="job-detail-<?= $row['id'] ?>" class="hidden bg-slate-50/50 dark:bg-slate-800/10 border-b border-[var(--border-color)]">
                                        <td colspan="6" class="p-4 pl-12 pr-8 text-[11px] text-[var(--text-sub)]">
                                            <div class="space-y-3">
                                                <h4 class="font-extrabold text-[var(--text-main)] flex items-center gap-1.5 border-b border-[var(--border-color)] pb-1.5">
                                                    <i class="fas fa-list-ul text-[#18aee7]"></i>
                                                    <span><?= $current_lang == 'KO' ? '작업 대상 및 이전 설정 상세' : 'Target Item Snapshots Before Change' ?></span>
                                                </h4>
                                                
                                                <div class="grid grid-cols-1 gap-2 max-h-[200px] overflow-y-auto style-scrollbar pr-2">
                                                    <?php if (is_array($snapshot)): foreach ($snapshot as $item): ?>
                                                        <div class="p-2.5 rounded-lg border border-[var(--border-color)] bg-[var(--card-bg)] flex justify-between items-center shadow-xs">
                                                            <div class="flex flex-col gap-0.5">
                                                                <span class="font-bold text-[var(--text-main)] text-[12px]"><?= htmlspecialchars($item['company'] ?? '') ?></span>
                                                                <span class="text-blue-500 dark:text-blue-400 font-mono"><?= htmlspecialchars($item['url'] ?? '') ?></span>
                                                            </div>
                                                            <div class="flex flex-wrap gap-2 justify-end text-[10px] text-[var(--text-sub)]">
                                                                <span class="bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded">
                                                                    <?= $current_lang == 'KO' ? '그룹' : 'Group' ?>: <b><?= htmlspecialchars($item['group_name'] ?? '-') ?></b>
                                                                </span>
                                                                <span class="bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded">
                                                                    <?= $current_lang == 'KO' ? '상태' : 'Status' ?>: <b><?= htmlspecialchars($item['check'] ?? '-') ?></b>
                                                                </span>
                                                                <span class="bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded">
                                                                    <?= $current_lang == 'KO' ? '엔진' : 'Engine' ?>: <b><?= htmlspecialchars($item['engine_type'] ?? 'lynx') ?></b>
                                                                </span>
                                                                <span class="bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded">
                                                                    <?= $current_lang == 'KO' ? '타임아웃' : 'Timeout' ?>: <b><?= htmlspecialchars($item['timeout_sec'] ?? '2') ?>s</b>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; endif; ?>
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
        </div>
        
        <!-- Footer -->
        <div class="px-8 py-4 border-t border-[var(--border-color)] flex justify-end bg-slate-50 dark:bg-slate-800/30">
            <button type="button" onclick="closeHistoryModal()" class="px-6 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-xs font-bold rounded-xl text-[var(--text-main)] transition-colors">
                <?= $current_lang == 'KO' ? '닫기' : 'Close' ?>
            </button>
        </div>
    </div>
</div>

<script>
function toggleJobDetail(id) {
    const detailRow = document.getElementById('job-detail-' + id);
    const arrow = document.getElementById('detail-arrow-' + id);
    if (detailRow && arrow) {
        if (detailRow.classList.contains('hidden')) {
            detailRow.classList.remove('hidden');
            arrow.classList.add('rotate-180');
        } else {
            detailRow.classList.add('hidden');
            arrow.classList.remove('rotate-180');
        }
    }
}

function confirmRollback(jobId, count, actionType) {
    const isKo = (document.documentElement.lang === 'ko' || '<?= $current_lang ?>' === 'KO');
    let actionLabel = actionType;
    if (actionType === 'bulk_update') actionLabel = isKo ? '일괄 수정' : 'Bulk Edit';
    else if (actionType === 'bulk_delete') actionLabel = isKo ? '일괄 삭제' : 'Bulk Delete';
    else if (actionType === 'delete') actionLabel = isKo ? '삭제' : 'Delete';

    const msg = isKo 
        ? `[작업 유형: ${actionLabel} (대상 수: ${count}개)]\n\n해당 작업을 정말로 원복하시겠습니까?\n이전의 설정 상태로 그대로 복구됩니다.`
        : `[Action: ${actionLabel} (Targets: ${count})]\n\nAre you sure you want to roll back this job?\nSettings will be restored to their previous states.`;
        
    if (confirm(msg)) {
        window.location.href = `website_status.php?action=rollback_job&job_id=${jobId}`;
    }
}
</script>
