<div class="py-3 pr-5 pl-7 flex items-center text-[13px] border-b border-[var(--border-color)] <?= $current_view == 'card' ? 'hidden' : 'flex' ?>">
    <div class="w-10"><input type="checkbox" name="chk_ids[]" value="<?=$row['no']?>" data-company="<?=htmlspecialchars($row['company'])?>" class="item-checkbox w-4 h-4 rounded accent-[#18aee7]"></div>
    <div class="flex-1">
        <span class="group-badge clickable" onclick="filterByGroup('<?=$group_name_display?>')"><?= $group_name_display ?></span>
    </div>
    <div class="flex-[1.2] font-black truncate pr-2"><?= htmlspecialchars($row['company']) ?></div>
    <div class="flex-[2.5] pl-4 min-w-0 pr-2">
        <a href="<?= htmlspecialchars($row['url']) ?>" target="_blank" class="block truncate text-[#18aee7] font-bold hover:underline" title="<?= htmlspecialchars($row['url']) ?>">
            <?= htmlspecialchars($row['url']) ?>
        </a>
    </div>
    <div class="flex-1 text-center text-[var(--text-sub)] truncate px-1"><?= htmlspecialchars($row['contents']) ?></div>
    <div class="flex-1 text-center font-bold <?= $status_class ?>"><?= $check_text ?></div>
    <div class="flex-1 text-center font-black <?= $result_color ?>"><?= $is_disabled ? '-' : $result_text ?></div>
    <div class="w-20 text-right pr-2 text-[var(--text-sub)] flex justify-end gap-3">
        <i class="fas fa-cog cursor-pointer hover:text-[#18aee7]" onclick='openModal("edit", <?= json_encode($row) ?>)'></i>
        <i class="fas fa-trash cursor-pointer hover:text-red-500" onclick="confirmDelete('<?=htmlspecialchars($row['company'])?>', <?=$row['no']?>)"></i>
    </div>
</div>