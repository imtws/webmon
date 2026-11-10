<div class="view-list flex flex-col gap-3">
    <?php if (!empty($log_rows)): ?>
        <?php foreach($log_rows as $log_row):
            $border_color = $log_row['result'] > 0 ? 'bg-[#18aee7]' : 'bg-red-500';
            $result_badge_class = $log_row['result'] > 0 
                ? 'bg-green-50 dark:bg-green-950/40 text-green-500 dark:text-green-400 border border-green-200/50 dark:border-green-800/50' 
                : 'bg-red-50 dark:bg-rose-950/40 text-red-500 dark:text-rose-400 border border-red-200/50 dark:border-rose-800/50';
            $result_text = $log_row['result'] > 0 ? $t['v_success'] : $t['v_fail'];
            $result_icon = $log_row['result'] > 0 ? 'fa-check-circle' : 'fa-exclamation-circle';
        ?>
            <div class="log-entry relative bg-[var(--card-bg)] border border-[var(--border-color)] rounded-xl py-3 pr-5 pl-7 flex items-center hover:shadow-md transition-all text-[13px] hover:z-[110]">
                <div class="absolute top-0 left-0 w-1.5 h-full <?= $border_color ?> rounded-l-xl"></div>
                <!-- 시각 -->
                <div class="w-48 text-[var(--text-sub)] dark:text-slate-300 font-mono">
                    <?= htmlspecialchars($log_row['log_time']) ?>
                </div>
                <!-- 그룹 -->
                <div class="w-40 font-semibold text-slate-500 dark:text-slate-300 truncate pr-4">
                    <?= htmlspecialchars($log_row['group_name'] ?: '기본 그룹') ?>
                </div>
                <!-- 업체명 -->
                <div class="w-48 font-black text-[var(--text-main)] dark:text-white truncate pr-4">
                    <?= htmlspecialchars($log_row['company']) ?>
                </div>
                <!-- URL / 실행 명령어 -->
                <div class="flex-1 pr-4 min-w-0">
                    <div class="flex flex-col gap-1">
                        <a href="<?= htmlspecialchars($log_row['req_url']) ?>" target="_blank" class="text-[#18aee7] hover:underline font-bold truncate max-w-full block">
                            <?= htmlspecialchars($log_row['req_url']) ?>
                        </a>
                        <?php if (!empty($log_row['cmd'])): ?>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <div class="relative group flex-1 min-w-0">
                                    <div class="bg-slate-100 dark:bg-slate-800/80 text-[10px] text-slate-600 dark:text-slate-200 font-mono rounded px-2 py-1 select-all truncate border border-slate-200 dark:border-slate-700/60 cursor-help" title="<?= htmlspecialchars($log_row['cmd']) ?>">
                                        <i class="fas fa-terminal text-[8px] mr-1 text-slate-400"></i><?= Utils::highlightCommand($log_row['cmd']) ?>
                                    </div>
                                    <div class="absolute bottom-full left-0 z-[120] mb-2 w-max max-w-lg hidden group-hover:block bg-slate-900 text-white text-[10px] rounded p-2 shadow-lg font-mono break-all border border-slate-700 cmd-tooltip">
                                        <?= Utils::highlightCommand($log_row['cmd']) ?>
                                    </div>
                                </div>
                                <button type="button" onclick="copyCmd(this)" data-cmd="<?= htmlspecialchars($log_row['cmd'], ENT_QUOTES, 'UTF-8') ?>" class="flex items-center justify-center p-1.5 rounded bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-[#18aee7] hover:text-[#18aee7] text-slate-400 transition-all cursor-pointer flex-shrink-0" title="<?= $current_lang == 'KO' ? '명령어 복사' : 'Copy command' ?>">
                                    <i class="far fa-copy text-[10px]"></i>
                                </button>
                            </div>
                        <?php else: ?>
                            <span class="text-[10px] text-gray-400 italic"><?= $current_lang == 'KO' ? '실행 명령 기록 없음' : 'No execution command logged' ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- 응답속도 -->
                <div class="w-28 text-center font-bold font-mono text-slate-700 dark:text-slate-200">
                    <?= number_format($log_row['response_time'], 3) ?>s
                </div>
                <!-- 결과 -->
                <div class="w-28 text-center">
                    <span class="inline-flex items-center justify-center px-2 py-1 text-[10px] font-black rounded-lg <?= $result_badge_class ?>">
                        <i class="fas <?= $result_icon ?> mr-1 text-[9px]"></i><?= $result_text ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if ($log_has_more): ?>
            <button type="button" class="load-more-logs" data-cursor="<?= $next_log_cursor ?>">
                <?= $current_lang == 'KO' ? '이전 로그 더 보기' : 'Load older logs' ?>
            </button>
        <?php endif; ?>
    <?php else: ?>
        <div class="bg-[var(--card-bg)] border border-[var(--border-color)] rounded-xl py-10 text-center text-[var(--text-sub)]">
            <?= $current_lang == 'KO' ? '조회된 로그가 없습니다.' : 'No logs found.' ?>
        </div>
    <?php endif; ?>
</div>
