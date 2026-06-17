<div class="py-3 pr-5 pl-7 flex items-center text-[13px] border-b border-[var(--border-color)] <?= $current_view == 'card' ? 'hidden' : 'flex' ?>">
    <div class="w-10"><input type="checkbox" name="chk_ids[]" value="<?=$row['no']?>" data-company="<?=htmlspecialchars($row['company'])?>" class="item-checkbox w-4 h-4 rounded accent-[#18aee7]"></div>
    <div class="flex-1">
        <span class="group-badge clickable" onclick="filterByGroup('<?=$group_name_display?>')"><?= $group_name_display ?></span>
    </div>
    <?php
    $ssl_res = getExpiryColorAndText($row['ssl_expiry'], $row['ssl_mon']);
    $dom_res = getExpiryColorAndText($row['dom_expiry'], $row['dom_mon']);

    if ($is_disabled) {
        $status_badge = 'bg-slate-100 dark:bg-slate-800 text-slate-500 px-2 py-0.5 rounded-full text-[9px] font-black inline-block';
    } elseif ($is_currently_off_schedule) {
        $status_badge = 'bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300 px-2 py-0.5 rounded-full text-[9px] font-black inline-block border border-violet-200/50 dark:border-violet-800/30';
    } else {
        $status_badge = ($row['check'] == 'alarm') 
            ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 px-2 py-0.5 rounded-full text-[9px] font-black inline-block border border-green-200/50 dark:border-green-800/30' 
            : 'bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300 px-2 py-0.5 rounded-full text-[9px] font-black inline-block border border-orange-200/50 dark:border-orange-800/30';
    }

    if ($is_currently_off_schedule) {
        $result_badge = 'bg-violet-500 text-white px-2 py-0.5 rounded-full text-[9px] font-black inline-block shadow-sm';
    } else {
        $result_badge = ($row['value'] > 0)
            ? 'bg-emerald-500 text-white px-2 py-0.5 rounded-full text-[9px] font-black inline-block shadow-sm shadow-emerald-500/10'
            : 'bg-red-500 text-white px-2 py-0.5 rounded-full text-[9px] font-black inline-block shadow-sm shadow-red-500/10 animate-pulse';
    }
    ?>
    <div class="flex-[1.2] font-black pr-2 min-w-0 flex flex-col justify-center">
        <div class="truncate text-[var(--text-main)]" title="<?= htmlspecialchars($row['company']) ?>"><?= htmlspecialchars($row['company']) ?></div>
        <div class="flex gap-1.5 text-[9px] mt-1 font-bold whitespace-nowrap">
            <?php if ($row['ssl_mon'] == 1 && $row['ssl_expiry']): 
                $ssl_badge_class = '';
                if (strpos($ssl_res['badge'], 'bg-red-500') !== false) $ssl_badge_class = 'bg-red-500 text-white';
                elseif (strpos($ssl_res['badge'], 'bg-amber-500') !== false) $ssl_badge_class = 'bg-amber-500 text-white shadow-sm shadow-amber-500/10';
                else $ssl_badge_class = 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400';
            ?>
                <span class="px-1.5 py-0.5 rounded text-[8px] font-black <?= $ssl_badge_class ?>" title="SSL 만료일: <?= $row['ssl_expiry'] ?>">SSL <?= $ssl_res['dday'] ?></span>
            <?php endif; ?>
            <?php if ($row['dom_mon'] == 1 && $row['dom_expiry']): 
                $dom_badge_class = '';
                if (strpos($dom_res['badge'], 'bg-red-500') !== false) $dom_badge_class = 'bg-red-500 text-white';
                elseif (strpos($dom_res['badge'], 'bg-amber-500') !== false) $dom_badge_class = 'bg-amber-500 text-white shadow-sm shadow-amber-500/10';
                else $dom_badge_class = 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400';
            ?>
                <span class="px-1.5 py-0.5 rounded text-[8px] font-black <?= $dom_badge_class ?>" title="도메인 만료일: <?= $row['dom_expiry'] ?>">DOM <?= $dom_res['dday'] ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="flex-[2.5] pl-4 min-w-0 pr-2">
        <a href="<?= htmlspecialchars($row['url']) ?>" target="_blank" class="block truncate text-[#18aee7] font-bold hover:underline" title="<?= htmlspecialchars($row['url']) ?>">
            <?= htmlspecialchars($row['url']) ?>
        </a>
    </div>
    <div class="flex-1 text-center text-[var(--text-sub)] truncate px-1"><?= htmlspecialchars($row['contents']) ?></div>
    <div class="flex-1 text-center"><span class="<?= $status_badge ?>"><?= $check_text ?></span></div>
    <div class="flex-1 text-center min-w-0 flex flex-col items-center justify-center">
        <span class="<?= $result_badge ?>"><?= $is_disabled ? '-' : $result_text ?></span>
        <?php if (!$is_disabled && $row['value'] == 0 && !empty($row['error_msg'])): ?>
            <span class="text-[9px] text-red-500 font-bold block mt-0.5 leading-none max-w-[90px] truncate" title="<?= htmlspecialchars($row['error_msg']) ?>">(<?= htmlspecialchars($row['error_msg']) ?>)</span>
        <?php endif; ?>
    </div>
    <div class="w-28 text-right pr-2 text-[var(--text-sub)] flex justify-end items-center gap-3">
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
        <i class="fas fa-sync-alt cursor-pointer hover:text-[#18aee7] diagnose-btn" title="<?= $current_lang == 'KO' ? '즉시 재진단 및 만료 정보 갱신' : 'Diagnose & Sync Expiry' ?>" onclick="diagnoseItem(<?=$row['no']?>, this)"></i>
        <i class="fas fa-cog cursor-pointer hover:text-[#18aee7]" onclick='openModal("edit", <?= json_encode($row) ?>)'></i>
        <i class="fas fa-trash cursor-pointer hover:text-red-500" onclick="confirmDelete('<?=htmlspecialchars($row['company'])?>', <?=$row['no']?>)"></i>
        <i class="fas fa-chevron-down cursor-pointer hover:text-[#18aee7] transition-transform duration-200" id="arrow-<?=$row['no']?>" onclick="toggleAccordion(event, <?=$row['no']?>)"></i>
    </div>
</div>

<!-- 아코디언 시계열 차트 영역 (리스트 뷰일 때만 동작하도록 설정) -->
<div id="accordion-<?=$row['no']?>" class="hidden w-full px-8 pb-5 pt-3 border-t border-[var(--border-color)] bg-slate-50/50 dark:bg-slate-800/20 flex-col gap-4 <?= $current_view == 'card' ? '!hidden' : '' ?>">
    <div class="flex justify-between items-center">
        <div class="flex items-center gap-2">
            <span class="text-xs font-bold text-[var(--text-sub)]"><i class="fas fa-chart-line"></i> <?= $current_lang == 'KO' ? '응답 속도 추이:' : 'Response Time Trend:' ?></span>
            <div class="inline-flex rounded-md shadow-sm" role="group">
                <button type="button" onclick="loadChart(<?=$row['no']?>, '1d')" class="px-3 py-1 text-xs font-bold text-gray-700 bg-white border border-gray-200 rounded-l-md hover:bg-gray-100 dark:bg-slate-700 dark:border-slate-600 dark:text-white dark:hover:bg-slate-600">1D</button>
                <button type="button" onclick="loadChart(<?=$row['no']?>, '7d')" class="px-3 py-1 text-xs font-bold text-gray-700 bg-white border-t border-b border-gray-200 hover:bg-gray-100 dark:bg-slate-700 dark:border-slate-600 dark:text-white dark:hover:bg-slate-600">7D</button>
                <button type="button" onclick="loadChart(<?=$row['no']?>, '30d')" class="px-3 py-1 text-xs font-bold text-gray-700 bg-white border border-gray-200 rounded-r-md hover:bg-gray-100 dark:bg-slate-700 dark:border-slate-600 dark:text-white dark:hover:bg-slate-600">30D</button>
            </div>
            
            <!-- 보기 옵션 체크박스 추가 -->
            <div class="flex items-center gap-3 ml-3 text-[11px] font-bold text-[var(--text-sub)] border-l border-[var(--border-color)] pl-3">
                <span><?= $current_lang == 'KO' ? '보기 옵션:' : 'Show:' ?></span>
                <label class="flex items-center gap-1 cursor-pointer select-none">
                    <input type="checkbox" id="show-time-<?=$row['no']?>" checked onchange="updateChartVisibility(<?=$row['no']?>)" class="w-3.5 h-3.5 accent-[#18aee7] rounded cursor-pointer">
                    <span><?= $current_lang == 'KO' ? '응답속도' : 'Response Time' ?></span>
                </label>
                <label class="flex items-center gap-1 cursor-pointer select-none">
                    <input type="checkbox" id="show-rate-<?=$row['no']?>" checked onchange="updateChartVisibility(<?=$row['no']?>)" class="w-3.5 h-3.5 accent-red-500 rounded cursor-pointer">
                    <span><?= $current_lang == 'KO' ? '장애 시점 표시' : 'Failure Points' ?></span>
                </label>
            </div>

            <div class="flex items-center gap-1.5 text-xs text-[var(--text-sub)] font-bold ml-2">
                <span><?= $current_lang == 'KO' ? '직접 선택:' : 'Custom:' ?></span>
                <input type="text" id="chart-start-<?=$row['no']?>" placeholder="YYYY-MM-DD HH:MM:SS" class="bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-1 text-[10px] text-[var(--text-main)] outline-none focus:border-[#18aee7] w-[135px]">
                <span>~</span>
                <input type="text" id="chart-end-<?=$row['no']?>" placeholder="YYYY-MM-DD HH:MM:SS" class="bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-1 text-[10px] text-[var(--text-main)] outline-none focus:border-[#18aee7] w-[135px]">
                <button type="button" onclick="loadCustomChart(<?=$row['no']?>)" class="px-2 py-1 bg-[#18aee7] hover:bg-[#159bd0] text-white rounded-lg font-bold text-[10px] transition-colors"><?= $current_lang == 'KO' ? '조회' : 'Go' ?></button>
            </div>
        </div>
        <div class="text-xs text-[var(--text-sub)] font-bold">
            <?= $current_lang == 'KO' ? '평균 응답속도' : 'Avg Time' ?>: <span id="avg-time-<?=$row['no']?>" class="text-blue-500 font-extrabold">-</span>s | 
            <?= $current_lang == 'KO' ? '성공률' : 'Success Rate' ?>: <span id="success-rate-<?=$row['no']?>" class="text-green-500 font-extrabold">-</span>%
        </div>
    </div>
    <div class="relative w-full h-[180px] bg-[var(--card-bg)] border border-[var(--border-color)] rounded-xl p-3 shadow-inner">
        <canvas id="chart-canvas-<?=$row['no']?>" class="w-full h-full"></canvas>
    </div>
    <div class="text-[9px] text-[var(--text-sub)] opacity-70 text-right -mt-2">
        <i class="fas fa-info-circle"></i> <?= $current_lang == 'KO' ? '조회 기간이 길어지면 다운샘플링이 적용되어 그래프 수치가 실제와 일부 다를 수 있습니다.' : 'Downsampling is applied for longer query ranges; trend values may slightly deviate from raw logs.' ?>
    </div>
</div>