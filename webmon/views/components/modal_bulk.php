<div id="bulkModal" class="side-sheet-overlay hidden fixed inset-0 z-[1100] justify-end bg-black/45">
    <div class="side-sheet-panel modal-content flex flex-col w-full max-w-xl h-full bg-[var(--card-bg)] shadow-2xl border-l border-[var(--border-color)] overflow-hidden" role="dialog" aria-modal="true">
        <h2 class="sheet-header">
            <span class="sheet-header-title">
                <i class="fas fa-sliders-h"></i><span><?= $current_lang == 'KO' ? '선택 항목 일괄 설정 변경' : 'Bulk Edit Selected Items' ?></span>
            </span>
            <span class="flex shrink-0 items-center gap-3">
                <span id="bulkModalCount" class="text-xs text-[var(--text-sub)] font-semibold"></span>
                <button type="button" class="sheet-close-button" aria-label="<?= $current_lang == 'KO' ? '닫기' : 'Close' ?>" onclick="closeBulkModal()"><i class="fas fa-times"></i></button>
            </span>
        </h2>
        
        <form id="bulkModalForm" method="POST" action="website_status.php" class="sheet-form flex min-h-0 flex-1 flex-col overflow-hidden">
            <input type="hidden" name="action" value="bulk_update">
            <input type="hidden" name="ids" id="bulkModalIds" value="">
            <input type="hidden" name="current_query" id="bulkModalCurrentQuery" value="">
            
            <div class="min-h-0 flex-1 overflow-y-auto p-5 style-scrollbar">
            <div class="space-y-4">
                <!-- 1. 그룹 설정 변경 -->
                <div class="bulk-section flex gap-3 items-start">
                    <input type="checkbox" name="chk_group" id="chkGroup" class="mt-1.5 w-4 h-4 rounded accent-[#18aee7] cursor-pointer" onchange="toggleBulkField('chkGroup', ['bulkGroupSelect', 'bulkGroupNew']); toggleBulkGroupNewInput();">
                    <div class="flex-1">
                        <label for="chkGroup" class="block text-xs font-black text-[var(--text-sub)] mb-1.5 cursor-pointer uppercase tracking-wider">
                            <?= $current_lang == 'KO' ? '그룹 변경' : 'Change Group' ?>
                        </label>
                        <div class="filter-control flex w-full items-center">
                            <i class="fas fa-folder text-[10px] text-slate-400 dark:text-slate-500 mr-2" aria-hidden="true"></i>
                            <select name="group_select" id="bulkGroupSelect" onchange="toggleBulkGroupNewInput()" disabled>
                                <?php foreach($existing_groups as $g): ?><option value="<?=htmlspecialchars($g)?>"><?=htmlspecialchars($g)?></option><?php endforeach; ?>
                                <option value="_NEW_"><?= $t['group_add_new'] ?></option>
                            </select>
                        </div>
                        <input type="text" name="group_name_new" id="bulkGroupNew" class="hidden mt-2 w-full p-2.5 border-l-4 border-l-[#18aee7] border border-[var(--border-color)] rounded-xl bg-[var(--bg-color)] disabled:opacity-40" placeholder="<?= $current_lang == 'KO' ? '새 그룹명 입력' : 'Enter a new group name' ?>" disabled>
                    </div>
                </div>

                <!-- 2. 알람 상태 변경 -->
                <div class="bulk-section flex gap-3 items-start">
                    <input type="checkbox" name="chk_check" id="chkCheck" class="mt-1.5 w-4 h-4 rounded accent-[#18aee7] cursor-pointer" onchange="toggleBulkField('chkCheck', ['bulkCheck'])">
                    <div class="flex-1">
                        <label for="chkCheck" class="block text-xs font-black text-[var(--text-sub)] mb-1.5 cursor-pointer uppercase tracking-wider">
                            <?= $current_lang == 'KO' ? '알람 상태 변경' : 'Change Alarm Status' ?>
                        </label>
                        <div class="filter-control flex w-full items-center">
                            <select name="check" id="bulkCheck" disabled>
                                <option value="alarm"><?= $t['status_alarm'] ?></option>
                                <option value="no alarm"><?= $t['status_noalarm'] ?></option>
                                <option value="disable"><?= $t['status_disable'] ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 3. 모니터링 엔진 변경 -->
                <div class="bulk-section flex gap-3 items-start">
                    <input type="checkbox" name="chk_engine" id="chkEngine" class="mt-1.5 w-4 h-4 rounded accent-[#18aee7] cursor-pointer" onchange="toggleBulkField('chkEngine', ['bulkEngineType'])">
                    <div class="flex-1">
                        <label for="chkEngine" class="block text-xs font-black text-[var(--text-sub)] mb-1.5 cursor-pointer uppercase tracking-wider">
                            <?= $current_lang == 'KO' ? '실행 엔진 변경' : 'Change Engine Type' ?>
                        </label>
                        <div class="filter-control flex w-full items-center">
                            <select name="engine_type" id="bulkEngineType" disabled>
                                <option value="lynx">LYNX</option>
                                <option value="curl">CURL</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 4. 체크 횟수 및 타임아웃 변경 -->
                <div class="bulk-section flex gap-3 items-start">
                    <input type="checkbox" name="chk_limits" id="chkLimits" class="mt-1.5 w-4 h-4 rounded accent-[#18aee7] cursor-pointer" onchange="toggleBulkField('chkLimits', ['bulkRetry', 'bulkTimeout'])">
                    <div class="flex-1 grid grid-cols-2 gap-3">
                        <div>
                            <label for="chkLimits" class="block text-xs font-black text-[var(--text-sub)] mb-1.5 cursor-pointer uppercase tracking-wider">
                                <?= $current_lang == 'KO' ? '체크 횟수 변경' : 'Change Retry Count' ?>
                            </label>
                            <div class="filter-control flex w-full items-center">
                                <select name="retry_count" id="bulkRetry" disabled>
                                    <option value="1">1<?= $current_lang == 'KO' ? '회 (기본)' : ' Time' ?></option>
                                    <option value="2">2<?= $current_lang == 'KO' ? '회' : ' Times' ?></option>
                                    <option value="3">3<?= $current_lang == 'KO' ? '회' : ' Times' ?></option>
                                    <option value="5">5<?= $current_lang == 'KO' ? '회' : ' Times' ?></option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label for="chkLimits" class="block text-xs font-black text-[var(--text-sub)] mb-1.5 cursor-pointer uppercase tracking-wider">
                                <?= $current_lang == 'KO' ? '타임아웃(초)' : 'Timeout (sec)' ?>
                            </label>
                            <input type="number" name="timeout_sec" id="bulkTimeout" class="w-full p-2.5 border border-[var(--border-color)] rounded-xl bg-[var(--bg-color)] text-[var(--text-main)] outline-none focus:border-[#18aee7] disabled:opacity-40" value="2" min="1" max="30" disabled>
                        </div>
                    </div>
                </div>

                <!-- 5. 만료일 모니터링 변경 -->
                <div class="bulk-section flex gap-3 items-start">
                    <input type="checkbox" name="chk_expiry" id="chkExpiry" class="mt-1.5 w-4 h-4 rounded accent-[#18aee7] cursor-pointer" onchange="toggleBulkField('chkExpiry', ['bulkSsl', 'bulkDom'])">
                    <div class="flex-1 grid grid-cols-2 gap-3">
                        <div>
                            <label for="chkExpiry" class="block text-xs font-black text-[var(--text-sub)] mb-1.5 cursor-pointer uppercase tracking-wider">SSL Expiry</label>
                            <div class="filter-control flex w-full items-center">
                                <select name="ssl_mon" id="bulkSsl" disabled><option value="1">ON</option><option value="0">OFF</option></select>
                            </div>
                        </div>
                        <div>
                            <label for="chkExpiry" class="block text-xs font-black text-[var(--text-sub)] mb-1.5 cursor-pointer uppercase tracking-wider">Domain Expiry</label>
                            <div class="filter-control flex w-full items-center">
                                <select name="dom_mon" id="bulkDom" disabled><option value="1">ON</option><option value="0">OFF</option></select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 6. 이메일 알림 방식 및 수/발신 주소 변경 -->
                <div class="bulk-section flex gap-3 items-start">
                    <input type="checkbox" name="chk_email" id="chkEmail" class="mt-1.5 w-4 h-4 rounded accent-[#18aee7] cursor-pointer" onchange="toggleBulkField('chkEmail', ['bulkEmailMode', 'bulkSenderEmail', 'bulkReceiverEmail']); toggleBulkSmtpVisibility();">
                    <div class="flex-1 space-y-3">
                        <div>
                            <label for="chkEmail" class="block text-xs font-black text-[var(--text-sub)] mb-1.5 cursor-pointer uppercase tracking-wider">
                                <?= $current_lang == 'KO' ? '이메일 알림 설정 변경' : 'Change Email Notification' ?>
                            </label>
                            <div class="filter-control flex w-full items-center">
                                <select name="email_mode" id="bulkEmailMode" onchange="toggleBulkSmtpVisibility()" disabled>
                                    <option value="none"><?= $current_lang == 'KO' ? '미발송 (사용 안 함)' : 'Disabled' ?></option>
                                    <option value="postfix"><?= $current_lang == 'KO' ? '수신 서버로 직접 발송' : 'Direct delivery' ?></option>
                                    <option value="smtp"><?= $current_lang == 'KO' ? '외부 SMTP 릴레이 발송 (기본설정 릴레이)' : 'SMTP Relay (System SMTP)' ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label for="chkEmail" class="block text-[10px] font-black text-[var(--text-sub)] mb-1 cursor-pointer">
                                <?= $current_lang == 'KO' ? '발송자 이메일' : 'Sender Email' ?>
                            </label>
                            <input type="email" name="sender_email" id="bulkSenderEmail" class="w-full p-2 border border-[var(--border-color)] rounded-lg bg-[var(--bg-color)] text-xs text-[var(--text-main)] outline-none focus:border-[#18aee7] disabled:opacity-40" placeholder="sender@example.com" disabled>
                        </div>
                        <div>
                            <label for="chkEmail" class="block text-[10px] font-black text-[var(--text-sub)] mb-1 cursor-pointer">
                                <?= $current_lang == 'KO' ? '수신자 이메일' : 'Receiver Email' ?>
                            </label>
                            <input type="email" name="receiver_email" id="bulkReceiverEmail" class="w-full p-2 border border-[var(--border-color)] rounded-lg bg-[var(--bg-color)] text-xs text-[var(--text-main)] outline-none focus:border-[#18aee7] disabled:opacity-40" placeholder="receiver@example.com" disabled>
                        </div>
                        </div>
                    </div>
                </div>

                <!-- 7. SMTP relay override -->
                <div class="bulk-section flex gap-3 items-start hidden" id="bulkSmtpContainer">
                    <input type="checkbox" name="chk_smtp" id="chkSmtp" class="mt-1.5 w-4 h-4 rounded accent-[#18aee7] cursor-pointer" onchange="toggleBulkField('chkSmtp', ['bulkSmtpHost', 'bulkSmtpPort', 'bulkSmtpSecure', 'bulkSmtpUser', 'bulkSmtpPass'])">
                    <div class="flex-1 space-y-3">
                        <label for="chkSmtp" class="block text-xs font-black text-[#18aee7] cursor-pointer uppercase tracking-wider">
                            <i class="fas fa-server mr-1"></i><?= $current_lang == 'KO' ? '개별 SMTP 서버 설정 변경' : 'Change Individual SMTP' ?>
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[9px] font-black text-[var(--text-sub)] mb-0.5">SMTP Host</label>
                                <input type="text" name="smtp_host" id="bulkSmtpHost" class="w-full p-2 border border-[var(--border-color)] rounded-lg bg-[var(--bg-color)] text-xs text-[var(--text-main)] outline-none focus:border-[#18aee7] disabled:opacity-40" placeholder="smtp.gmail.com" disabled>
                            </div>
                            <div>
                                <label class="block text-[9px] font-black text-[var(--text-sub)] mb-0.5">SMTP Port</label>
                                <input type="number" name="smtp_port" id="bulkSmtpPort" class="w-full p-2 border border-[var(--border-color)] rounded-lg bg-[var(--bg-color)] text-xs text-[var(--text-main)] outline-none focus:border-[#18aee7] disabled:opacity-40" placeholder="25" value="25" disabled>
                            </div>
                            <div>
                                <label class="block text-[9px] font-black text-[var(--text-sub)] mb-0.5">Secure Mode</label>
                                <div class="filter-control flex w-full items-center">
                                    <select name="smtp_secure" id="bulkSmtpSecure" disabled>
                                        <option value="none">None</option>
                                        <option value="tls">TLS (587)</option>
                                        <option value="ssl">SSL (465)</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[9px] font-black text-[var(--text-sub)] mb-0.5">SMTP Password</label>
                                <input type="password" name="smtp_pass" id="bulkSmtpPass" class="w-full p-2 border border-[var(--border-color)] rounded-lg bg-[var(--bg-color)] text-xs text-[var(--text-main)] outline-none focus:border-[#18aee7] disabled:opacity-40" placeholder="Password" disabled>
                            </div>
                            <div class="col-span-2">
                                <label class="block text-[9px] font-black text-[var(--text-sub)] mb-0.5">SMTP Username</label>
                                <input type="text" name="smtp_user" id="bulkSmtpUser" class="w-full p-2 border border-[var(--border-color)] rounded-lg bg-[var(--bg-color)] text-xs text-[var(--text-main)] outline-none focus:border-[#18aee7] disabled:opacity-40" placeholder="user@gmail.com" disabled>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bulk-section flex gap-3 items-start">
                    <input type="checkbox" name="chk_schedule" id="chkSchedule" class="mt-1.5 w-4 h-4 rounded accent-[#18aee7] cursor-pointer" onchange="toggleBulkField('chkSchedule', ['bulkScheduleAction', 'bulkMuteUntil']); toggleBulkScheduleFields();">
                    <div class="flex-1 space-y-3">
                        <label for="chkSchedule" class="block text-xs font-black text-[var(--text-sub)] mb-1.5 cursor-pointer">
                            <?= $current_lang == 'KO' ? '알람 일시 무음 스케줄 변경' : 'Change temporary mute schedule' ?>
                        </label>
                        <div class="filter-control flex w-full items-center">
                            <select name="sched_enabled" id="bulkScheduleAction" disabled onchange="toggleBulkScheduleFields()">
                                <option value="1"><?= $current_lang == 'KO' ? '지정한 시각까지 무음' : 'Mute until specified time' ?></option>
                                <option value="0"><?= $current_lang == 'KO' ? '무음 스케줄 해제' : 'Clear mute schedule' ?></option>
                            </select>
                        </div>
                        <div id="bulkMuteUntilContainer">
                            <label for="bulkMuteUntil" class="mb-1 block text-[11px] font-semibold text-[var(--text-sub)]"><?= $current_lang == 'KO' ? '무음 종료 시각' : 'Mute until' ?></label>
                            <input type="datetime-local" name="mute_until" id="bulkMuteUntil" class="w-full disabled:opacity-50" disabled>
                            <p class="mt-1 text-[10px] text-[var(--text-sub)]"><?= $current_lang == 'KO' ? '기본값: 지금부터 2시간 후 (비워두어도 동일하게 적용됩니다)' : 'Default: 2 hours from now (also used if left blank)' ?></p>
                        </div>
                        <p class="text-[11px] leading-relaxed text-[var(--text-sub)]">
                            <?= $current_lang == 'KO' ? '선택한 모든 항목에 같은 종료 시각을 적용합니다. 스케줄 해제 시 즉시 알림이 다시 활성화됩니다.' : 'Applies the same end time to all selected items. Clearing the schedule resumes alerts immediately.' ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- 경고 문구 및 버튼 영역 -->
            <div class="mt-5 border-t border-[var(--border-color)] pt-4 flex flex-col gap-4">
                <div class="flex gap-2 rounded-xl border border-[var(--border-color)] bg-[var(--bg-color)] p-3 text-xs leading-relaxed text-[var(--text-sub)]">
                    <i class="fas fa-info-circle mt-0.5 text-[#18aee7]"></i>
                    <div>
                        <?= $current_lang == 'KO' 
                            ? '변경할 설정 왼쪽의 체크박스를 선택하세요. 선택하지 않은 설정은 그대로 유지되며, 적용 내역은 작업 이력에서 원복할 수 있습니다.'
                            : 'Select the checkbox beside each setting you want to change. Unselected settings remain unchanged; applied changes can be reverted from Job History.'
                        ?>
                    </div>
                </div>

            </div>
            </div>
            <div class="sheet-footer">
                <button type="submit" class="px-10 py-3 bg-[#18aee7] hover:bg-[#159bd0] text-white rounded-xl text-sm font-black shadow-lg hover:shadow-xl transition-all text-xs"><?= $t['modal_save'] ?? '일괄 변경 적용' ?></button>
            </div>
        </form>
    </div>
</div>
