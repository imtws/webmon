    <!-- 🔔 개별 모니터링 알람 일시 무음 설정 모달 ────────────────────────────── -->
    <div id="itemScheduleModal" class="side-sheet-overlay fixed inset-0 z-[1000] hidden justify-end bg-black/40">
        <div class="side-sheet-panel bg-[var(--card-bg)] border-l border-[var(--border-color)] w-full max-w-md h-full shadow-2xl flex flex-col overflow-y-auto" role="dialog" aria-modal="true">
            <div class="sheet-header">
                <h3 class="sheet-header-title"><i class="fas fa-bell-slash"></i><span id="schedModalTitle">알람 일시 무음 설정</span></h3>
                <button type="button" class="sheet-close-button" aria-label="<?= $current_lang == 'KO' ? '닫기' : 'Close' ?>" onclick="closeItemScheduleModal()"><i class="fas fa-times"></i></button>
            </div>
            
            <form action="save_item_schedule.php" method="POST" class="sheet-form flex flex-col gap-4 p-5 text-sm">
                <input type="hidden" name="no" id="schedItemNo" value="">
                
                <!-- 무음 활성화 토글 -->
                <div class="flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/20 p-3 rounded-xl border border-[var(--border-color)]">
                    <div>
                        <span class="font-bold block"><?= $current_lang == 'KO' ? '일시 무음 설정 사용' : 'Enable Temporary Mute' ?></span>
                        <span class="text-[10px] text-[var(--text-sub)]"><?= $current_lang == 'KO' ? '지정한 일시까지 알람을 일시적으로 중지합니다.' : 'Temporarily disables notifications until the specified date and time.' ?></span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="sched_enabled" id="schedEnabled" value="1" class="sr-only peer" onchange="toggleSchedFields(this.checked)">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-[#18aee7]"></div>
                    </label>
                </div>

                <div id="schedFields" class="flex flex-col gap-4 transition-all duration-300">
                    <!-- 무음 종료 일시 설정 -->
                    <div>
                        <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '무음 종료 일시' : 'Mute Until (Date & Time)' ?></label>
                        <input type="datetime-local" name="mute_until" id="schedMuteUntil" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]" required>
                    </div>
                </div>

                <div class="sheet-footer -mx-5 -mb-5 mt-4">
                    <button type="submit" class="px-6 py-2.5 bg-[#18aee7] hover:bg-[#159bd0] text-white rounded-xl font-bold shadow-md transition-all text-xs"><?= $current_lang == 'KO' ? '스케줄 저장' : 'Save Schedule' ?></button>
                </div>
            </form>
        </div>
    </div>

