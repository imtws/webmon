<div class="monitor-card-body relative <?= $current_view == 'list' ? 'hidden' : 'block' ?>">
    <div class="monitor-card-header">
        <input type="checkbox" name="chk_ids[]" value="<?=$row['no']?>" data-company="<?=htmlspecialchars($row['company'])?>" class="item-checkbox h-4 w-4 rounded accent-[#18aee7] cursor-pointer">
        <div class="min-w-0 flex-1">
            <h2 class="truncate text-[17px] font-bold text-[var(--text-main)]"><?= htmlspecialchars($row['company']) ?></h2>
            <span class="group-badge clickable" onclick='filterByGroup(<?= json_encode($row['group_name'] ?: '기본 그룹', JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'><?= htmlspecialchars($row['group_name'] ?: '기본 그룹') ?></span>
        </div>
        <details class="monitor-actions">
            <summary aria-label="<?= $current_lang == 'KO' ? '항목 작업' : 'Monitor actions' ?>" title="<?= $current_lang == 'KO' ? '항목 작업' : 'Monitor actions' ?>"><i class="fas fa-ellipsis"></i></summary>
            <div class="monitor-actions-menu">
            <?php
            $bell_color = '';
            $bell_title = $current_lang == 'KO' ? '알람 일시 무음 설정' : 'Mute Settings';
            if ($is_scheduled && !empty($row['mute_until'])) {
                if ($is_currently_off_schedule) {
                    $bell_color = 'text-violet-500';
                    $bell_title = ($current_lang == 'KO' ? '일시 무음 적용 중 (종료: ' : 'Muted until (End: ') . htmlspecialchars($row['mute_until']) . ')';
                } else {
                    $bell_color = 'text-[#18aee7]';
                    $bell_title = ($current_lang == 'KO' ? '일시 무음 기한 만료 (기한: ' : 'Mute expired (Limit: ') . htmlspecialchars($row['mute_until']) . ')';
                }
            }
            ?>
            <button type="button" class="monitor-action <?= $bell_color ?>" title="<?= $bell_title ?>" onclick='openScheduleModal(event, <?= json_encode($row) ?>)'><i class="fas fa-bell"></i><span><?= $current_lang == 'KO' ? '알람 일시 무음' : 'Mute schedule' ?></span></button>
            <button type="button" class="monitor-action" title="<?= $current_lang == 'KO' ? '응답 추이 그래프 보기' : 'View response trend' ?>" onclick="toggleViewMode('list', <?=$row['no']?>)"><i class="fas fa-chart-line"></i><span><?= $current_lang == 'KO' ? '응답 추이' : 'Response trend' ?></span></button>
            <button type="button" class="monitor-action diagnose-btn" title="<?= $current_lang == 'KO' ? '즉시 재진단 및 만료 정보 갱신' : 'Diagnose and sync expiry' ?>" onclick="diagnoseItem(<?=$row['no']?>, this)"><i class="fas fa-sync-alt"></i><span><?= $current_lang == 'KO' ? '지금 재진단' : 'Run check now' ?></span></button>
            <button type="button" class="monitor-action" onclick='openModal("edit", <?= json_encode($row) ?>)'><i class="fas fa-pen"></i><span><?= $current_lang == 'KO' ? '설정 수정' : 'Edit settings' ?></span></button>
            <button type="button" class="monitor-action is-danger" onclick="confirmDelete(<?= json_encode($row['company'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>, <?=$row['no']?>)"><i class="fas fa-trash"></i><span><?= $current_lang == 'KO' ? '삭제' : 'Delete' ?></span></button>
            </div>
        </details>
    </div>
    
    <div class="monitor-card-url">
        <p class="truncate text-[12px] font-medium text-[#18aee7]">
            <a href="<?= htmlspecialchars($row['url']) ?>" target="_blank" class="hover:underline flex items-center gap-1">
                <i class="fas fa-link text-[10px] opacity-70"></i><?= htmlspecialchars($row['url']) ?>
            </a>
        </p>
    </div>

    <?php
    $ssl_res = getExpiryColorAndText($row['ssl_expiry'], $row['ssl_mon']);
    $dom_res = getExpiryColorAndText($row['dom_expiry'], $row['dom_mon']);
    ?>
    <!-- iOS 설정 그룹 스타일의 인포 목록 -->
    <div class="monitor-card-details">
        <!-- SSL 만료일 행 -->
        <div class="monitor-detail-row">
            <span><?= $t['h_ssl'] ?></span>
            <span class="font-semibold text-[var(--text-main)]"><?= $row['ssl_expiry'] ? date('Y-m-d', strtotime($row['ssl_expiry'])) : '-' ?><?= $ssl_res['badge'] ?></span>
        </div>
        
        <!-- 도메인 만료일 행 -->
        <div class="monitor-detail-row">
            <span><?= $t['h_dom'] ?></span>
            <span class="font-semibold text-[var(--text-main)]"><?= $row['dom_expiry'] ? date('Y-m-d', strtotime($row['dom_expiry'])) : '-' ?><?= $dom_res['badge'] ?></span>
        </div>

        <!-- 키워드 행 -->
        <div class="monitor-detail-row">
            <span><?= $t['h_keyword'] ?></span>
            <span class="max-w-[65%] truncate font-semibold text-[var(--text-main)]" title="<?= htmlspecialchars($row['contents']) ?>"><?= htmlspecialchars($row['contents']) ?></span>
        </div>
    </div>

    <?php
    if ($is_disabled) {
        $status_badge = 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-300 px-2 py-0.5 rounded-lg text-[9px] font-bold';
    } elseif ($is_currently_off_schedule) {
        $status_badge = 'bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300 px-2 py-0.5 rounded-lg text-[9px] font-bold border border-violet-200/50 dark:border-violet-800/30';
    } else {
        $status_badge = ($row['check'] == 'alarm') 
            ? 'bg-sky-50 dark:bg-sky-950/30 text-[#159bd0] px-2 py-0.5 rounded-lg text-[9px] font-bold border border-sky-200/70 dark:border-sky-800/40'
            : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2 py-0.5 rounded-lg text-[9px] font-bold border border-slate-200/70 dark:border-slate-700/50';
    }

    if ($is_currently_off_schedule) {
        $result_badge = 'bg-violet-500 text-white px-2 py-0.5 rounded-lg text-[9px] font-bold shadow-sm';
    } else {
        $result_badge = ($row['value'] > 0)
            ? 'bg-emerald-500 text-white px-2 py-0.5 rounded-lg text-[9px] font-bold shadow-sm shadow-emerald-500/10'
            : 'bg-red-500 text-white px-2 py-0.5 rounded-lg text-[9px] font-bold';
    }
    ?>
    <!-- 하단 상태 및 상세 설정 메타 배지 영역 (iOS Pills Layout) -->
    <div class="monitor-card-footer">
        <!-- 알람 상태 및 검사 결과 -->
        <div class="flex items-center justify-between text-xs">
            <div class="flex items-center gap-1.5">
                <span class="text-slate-500 dark:text-slate-300 font-bold text-[10px] uppercase"><?= $t['h_alarm'] ?></span>
                <span class="<?= $status_badge ?>"><?= $check_text ?></span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="text-slate-500 dark:text-slate-300 font-bold text-[10px] uppercase"><?= $t['h_result'] ?></span>
                <div class="flex flex-col items-end">
                    <span class="<?= $result_badge ?>"><?= $is_disabled ? '-' : $result_text ?></span>
                    <?php if (!$is_disabled && $row['value'] == 0 && !empty($row['error_msg'])): ?>
                        <span class="text-[9px] text-red-500 font-bold mt-0.5 max-w-[120px] truncate" title="<?= htmlspecialchars($row['error_msg']) ?>">(<?= htmlspecialchars($row['error_msg']) ?>)</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 엔진 / 타임아웃 / 재시도 설정 칩 (iOS System Badges Row) -->
        <?php
        $engine_val = strtolower($row['engine_type'] ?? 'lynx');
        $engine_class = 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200/60 dark:border-slate-700/50';

        $timeout_val = $row['timeout_sec'] ?? 2;
        $retry_val = $row['retry_count'] ?? 1;
        $detail_class = 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200/60 dark:border-slate-700/50';
        ?>
        <div class="flex items-center gap-1.5 flex-wrap">
            <span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold uppercase <?= $engine_class ?>">
                <?= strtoupper($engine_val) ?>
            </span>
            <span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold uppercase <?= $detail_class ?>">
                TIMEOUT: <?= $timeout_val ?>s
            </span>
            <span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold uppercase <?= $detail_class ?>">
                RETRY: <?= $retry_val ?><?= $current_lang == 'KO' ? '회' : 'x' ?>
            </span>
        </div>
    </div>
</div>