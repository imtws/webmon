<div class="p-5 relative <?= $current_view == 'list' ? 'hidden' : 'block' ?>">
    <div class="flex justify-between items-start mb-3">
        <div class="flex items-center gap-3">
            <input type="checkbox" name="chk_ids[]" value="<?=$row['no']?>" data-company="<?=htmlspecialchars($row['company'])?>" class="item-checkbox w-4 h-4 rounded accent-[#18aee7] cursor-pointer">
            <span class="group-badge clickable" onclick="filterByGroup('<?=$group_name_display?>')"># <?= $group_name_display ?></span>
        </div>
        <div class="flex gap-3 text-[var(--text-sub)] items-center">
            <?php
            $bell_color = 'hover:text-yellow-500';
            $bell_title = $current_lang == 'KO' ? '알람 일시 무음 설정' : 'Mute Settings';
            if ($is_scheduled && !empty($row['mute_until'])) {
                if ($is_currently_off_schedule) {
                    $bell_color = 'text-violet-400 hover:text-violet-500';
                    $bell_title = ($current_lang == 'KO' ? '일시 무음 적용 중 (종료: ' : 'Muted until (End: ') . htmlspecialchars($row['mute_until']) . ')';
                } else {
                    $bell_color = 'text-[#18aee7] hover:text-blue-500';
                    $bell_title = ($current_lang == 'KO' ? '일시 무음 기한 만료 (기한: ' : 'Mute expired (Limit: ') . htmlspecialchars($row['mute_until']) . ')';
                }
            }
            ?>
            <i class="fas fa-bell cursor-pointer <?= $bell_color ?>" title="<?= $bell_title ?>" onclick='openScheduleModal(event, <?= json_encode($row) ?>)'></i>
            <i class="fas fa-chart-line cursor-pointer hover:text-[#18aee7]" title="<?= $current_lang == 'KO' ? '응답 추이 그래프 보기' : 'View Response Trend Graph' ?>" onclick="toggleViewMode('list', <?=$row['no']?>)"></i>
            <i class="fas fa-sync-alt cursor-pointer hover:text-[#18aee7] diagnose-btn" title="<?= $current_lang == 'KO' ? '즉시 재진단 및 만료 정보 갱신' : 'Diagnose & Sync Expiry' ?>" onclick="diagnoseItem(<?=$row['no']?>, this)"></i>
            <i class="fas fa-cog cursor-pointer hover:text-[#18aee7]" onclick='openModal("edit", <?= json_encode($row) ?>)'></i>
            <i class="fas fa-trash cursor-pointer hover:text-red-500" onclick="confirmDelete('<?=htmlspecialchars($row['company'])?>', <?=$row['no']?>)"></i>
        </div>
    </div>
    
    <div class="mb-4">
        <span class="text-[10px] font-bold text-slate-500 dark:text-slate-350 tracking-wider block mb-1 uppercase"><?= $t['h_company'] ?></span>
        <h2 class="text-[16px] font-extrabold truncate text-[var(--text-main)] mb-1.5"><?= htmlspecialchars($row['company']) ?></h2>
        <span class="text-[10px] font-bold text-slate-500 dark:text-slate-350 tracking-wider block mb-1 uppercase"><?= $t['h_url'] ?></span>
        <p class="text-[12px] text-[#18aee7] font-semibold truncate">
            <a href="<?= htmlspecialchars($row['url']) ?>" target="_blank" class="hover:underline flex items-center gap-1">
                <i class="fas fa-link text-[10px] opacity-60"></i><?= htmlspecialchars($row['url']) ?>
            </a>
        </p>
    </div>

    <?php
    $ssl_res = getExpiryColorAndText($row['ssl_expiry'], $row['ssl_mon']);
    $dom_res = getExpiryColorAndText($row['dom_expiry'], $row['dom_mon']);
    ?>
    <!-- iOS 설정 그룹 스타일의 인포 목록 -->
    <div class="space-y-2 mb-4">
        <!-- SSL 만료일 행 -->
        <div class="flex items-center justify-between bg-slate-50 dark:bg-slate-800/40 p-2.5 rounded-xl border border-slate-100 dark:border-slate-600/70">
            <div class="flex items-center gap-2">
                <div class="w-6 h-6 rounded-lg bg-blue-500 text-white flex items-center justify-center text-[10px] shadow-sm shadow-blue-500/20">
                    <i class="fas fa-lock"></i>
                </div>
                <span class="text-[11px] font-bold text-slate-500 dark:text-slate-300"><?= $t['h_ssl'] ?></span>
            </div>
            <div class="flex items-center">
                <span class="text-[11px] font-extrabold text-slate-800 dark:text-white"><?= $row['ssl_expiry'] ? date('Y-m-d', strtotime($row['ssl_expiry'])) : '-' ?></span>
                <?= $ssl_res['badge'] ?>
            </div>
        </div>
        
        <!-- 도메인 만료일 행 -->
        <div class="flex items-center justify-between bg-slate-50 dark:bg-slate-800/40 p-2.5 rounded-xl border border-slate-100 dark:border-slate-600/70">
            <div class="flex items-center gap-2">
                <div class="w-6 h-6 rounded-lg bg-indigo-500 text-white flex items-center justify-center text-[10px] shadow-sm shadow-indigo-500/20">
                    <i class="fas fa-globe"></i>
                </div>
                <span class="text-[11px] font-bold text-slate-500 dark:text-slate-300"><?= $t['h_dom'] ?></span>
            </div>
            <div class="flex items-center">
                <span class="text-[11px] font-extrabold text-slate-800 dark:text-white"><?= $row['dom_expiry'] ? date('Y-m-d', strtotime($row['dom_expiry'])) : '-' ?></span>
                <?= $dom_res['badge'] ?>
            </div>
        </div>

        <!-- 키워드 행 -->
        <div class="flex items-center justify-between bg-slate-50 dark:bg-slate-800/40 p-2.5 rounded-xl border border-slate-100 dark:border-slate-600/70">
            <div class="flex items-center gap-2 min-w-0 flex-1">
                <div class="w-6 h-6 rounded-lg bg-amber-500 text-white flex items-center justify-center text-[10px] shadow-sm shadow-amber-500/20 flex-shrink-0">
                    <i class="fas fa-search"></i>
                </div>
                <span class="text-[11px] font-bold text-slate-500 dark:text-slate-300 flex-shrink-0 mr-2"><?= $t['h_keyword'] ?></span>
            </div>
            <div class="flex items-center min-w-0">
                <span class="text-[11px] font-extrabold text-slate-800 dark:text-white truncate max-w-[130px]" title="<?= htmlspecialchars($row['contents']) ?>"><?= htmlspecialchars($row['contents']) ?></span>
            </div>
        </div>
    </div>

    <?php
    if ($is_disabled) {
        $status_badge = 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-300 px-2 py-0.5 rounded-lg text-[9px] font-bold';
    } elseif ($is_currently_off_schedule) {
        $status_badge = 'bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300 px-2 py-0.5 rounded-lg text-[9px] font-bold border border-violet-200/50 dark:border-violet-800/30';
    } else {
        $status_badge = ($row['check'] == 'alarm') 
            ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-350 px-2 py-0.5 rounded-lg text-[9px] font-bold border border-green-200/50 dark:border-green-800/30' 
            : 'bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-350 px-2 py-0.5 rounded-lg text-[9px] font-bold border border-orange-200/50 dark:border-orange-800/30';
    }

    if ($is_currently_off_schedule) {
        $result_badge = 'bg-violet-500 text-white px-2 py-0.5 rounded-lg text-[9px] font-bold shadow-sm';
    } else {
        $result_badge = ($row['value'] > 0)
            ? 'bg-emerald-500 text-white px-2 py-0.5 rounded-lg text-[9px] font-bold shadow-sm shadow-emerald-500/10'
            : 'bg-red-500 text-white px-2 py-0.5 rounded-lg text-[9px] font-bold shadow-sm shadow-red-500/10 animate-pulse';
    }
    ?>
    <!-- 하단 상태 및 상세 설정 메타 배지 영역 (iOS Pills Layout) -->
    <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800/60 flex flex-col gap-2.5">
        <!-- 알람 상태 및 검사 결과 -->
        <div class="flex items-center justify-between text-xs">
            <div class="flex items-center gap-1.5">
                <span class="text-slate-400 dark:text-slate-350 font-bold text-[10px] uppercase"><?= $t['h_alarm'] ?></span>
                <span class="<?= $status_badge ?>"><?= $check_text ?></span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="text-slate-400 dark:text-slate-350 font-bold text-[10px] uppercase"><?= $t['h_result'] ?></span>
                <div class="flex flex-col items-end">
                    <span class="<?= $result_badge ?>"><?= $is_disabled ? '-' : $result_text ?></span>
                    <?php if (!$is_disabled && $row['value'] == 0 && !empty($row['error_msg'])): ?>
                        <span class="text-[9px] text-red-500 font-bold mt-0.5 max-w-[120px] truncate" title="<?= htmlspecialchars($row['error_msg']) ?>">(<?= htmlspecialchars($row['error_msg']) ?>)</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 엔진 / 타임아웃 / 재시도 설정 칩 (iOS System Badges Row) -->
        <div class="flex items-center gap-1.5 flex-wrap">
            <span class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-850 text-slate-500 dark:text-slate-300 text-[9px] font-extrabold uppercase border border-slate-200/50 dark:border-slate-700/50">
                <?= strtoupper($row['engine_type'] ?? 'lynx') ?>
            </span>
            <span class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-850 text-slate-500 dark:text-slate-300 text-[9px] font-extrabold uppercase border border-slate-200/50 dark:border-slate-700/50">
                TIMEOUT: <?= $row['timeout_sec'] ?? 2 ?>s
            </span>
            <span class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-850 text-slate-500 dark:text-slate-300 text-[9px] font-extrabold uppercase border border-slate-200/50 dark:border-slate-700/50">
                RETRY: <?= $row['retry_count'] ?? 1 ?><?= $current_lang == 'KO' ? '회' : 'x' ?>
            </span>
        </div>
    </div>
</div>