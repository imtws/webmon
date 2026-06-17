<div class="p-5 relative <?= $current_view == 'list' ? 'hidden' : 'block' ?>">
    <div class="flex justify-between items-start mb-3">
        <div class="flex items-center gap-3">
            <input type="checkbox" name="chk_ids[]" value="<?=$row['no']?>" data-company="<?=htmlspecialchars($row['company'])?>" class="item-checkbox w-4 h-4 rounded accent-[#18aee7]">
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
            <i class="fas fa-sync-alt cursor-pointer hover:text-[#18aee7] diagnose-btn" title="<?= $current_lang == 'KO' ? '즉시 재진단 및 만료일 동기화' : 'Diagnose & Sync Now' ?>" onclick="diagnoseItem(<?=$row['no']?>, this)"></i>
            <i class="fas fa-cog cursor-pointer hover:text-[#18aee7]" onclick='openModal("edit", <?= json_encode($row) ?>)'></i>
            <i class="fas fa-trash cursor-pointer hover:text-red-500" onclick="confirmDelete('<?=htmlspecialchars($row['company'])?>', <?=$row['no']?>)"></i>
        </div>
    </div>
    
    <div class="mb-4">
        <label class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-tighter">[<?= $t['h_company'] ?>]</label>
        <h2 class="text-[16px] font-black truncate text-[var(--text-main)]"><?= htmlspecialchars($row['company']) ?></h2>
        <label class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase mt-2 block tracking-tighter">[<?= $t['h_url'] ?>]</label>
        <p class="text-[12px] text-[#18aee7] font-bold truncate">
            <a href="<?= htmlspecialchars($row['url']) ?>" target="_blank" class="hover:underline">
                <?= htmlspecialchars($row['url']) ?>
            </a>
        </p>
    </div>

    <div class="grid grid-cols-2 gap-2 mb-4 bg-[var(--bg-color)] p-3 rounded-lg border border-[var(--border-color)]">
    <div>
        <span class="text-[9px] font-black text-gray-400 dark:text-gray-500 block"><?= $t['h_ssl'] ?></span>
        <span class="text-[11px] font-bold <?= (strtotime($row['ssl_expiry']) < strtotime('+7 days') && $row['ssl_expiry']) ? 'text-red-500' : '' ?>"><?= $row['ssl_expiry'] ?: '-' ?></span>
    </div>
    <div>
        <span class="text-[9px] font-black text-gray-400 dark:text-gray-500 block"><?= $t['h_dom'] ?></span>
        <span class="text-[11px] font-bold"><?= $row['dom_expiry'] ?: '-' ?></span>
    </div>
    <div class="col-span-2 border-t border-dashed border-[var(--border-color)] pt-2 mt-1">
        <span class="text-[9px] font-black text-gray-400 dark:text-gray-500 block"><?= $t['h_keyword'] ?></span>
        <span class="text-[11px] font-bold text-[var(--text-main)] block truncate" title="<?= htmlspecialchars($row['contents']) ?>"><?= htmlspecialchars($row['contents']) ?></span>
    </div>
</div>

    <div class="flex justify-between items-center text-[11px] border-t border-[var(--border-color)] pt-3">
        <div><span class="text-[var(--text-sub)]"><?= $t['h_alarm'] ?>:</span> <span class="<?= $status_class ?> font-black"><?= $check_text ?></span></div>
        <div><span class="text-[var(--text-sub)]"><?= $t['h_result'] ?>:</span> <span class="<?= $result_color ?> font-black"><?= $is_disabled ? '-' : $result_text ?></span></div>
    </div>
    <div class="flex flex-col gap-1 text-[11px] mt-2 pt-2 border-t border-dashed border-[var(--border-color)]">
        <div class="flex justify-between items-center">
            <div>
                <span class="text-[var(--text-sub)] uppercase">ENGINE :</span> 
                <span class="font-black text-[#18aee7]"><?= strtoupper($row['engine_type'] ?? 'lynx') ?></span>
            </div>
            <div class="text-right">
                <span class="text-[var(--text-sub)] uppercase">TIMEOUT :</span>
                <span class="font-black text-[var(--text-main)]"><?= $row['timeout_sec'] ?? 2 ?>s</span>
            </div>
        </div>
        
        <div>
            <span class="text-[var(--text-sub)] uppercase">RETRY :</span> 
            <span class="font-black text-[#18aee7]">
                <?= $row['retry_count'] ?? 1 ?><?= $current_lang == 'KO' ? '회' : ' Time(s)' ?>
            </span>
        </div>
    </div>
</div>