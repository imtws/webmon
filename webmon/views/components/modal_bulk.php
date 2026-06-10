<div id="bulkModal" class="hidden fixed inset-0 z-[200] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
    <div class="modal-content flex flex-col w-full max-w-[550px] bg-[var(--card-bg)] rounded-2xl shadow-2xl border border-[var(--border-color)] max-h-[90vh] overflow-hidden">
        <h2 class="text-xl font-black mb-4 text-[var(--text-main)] border-b border-[var(--border-color)] pb-4 p-8 flex justify-between items-center">
            <span>
                <i class="fas fa-sliders-h mr-2 text-[#18aee7]"></i><?= $current_lang == 'KO' ? '선택 항목 일괄 설정 변경' : 'Bulk Edit Selected Items' ?>
            </span>
            <span id="bulkModalCount" class="text-xs bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300 px-2.5 py-1 rounded-full font-bold"></span>
        </h2>
        
        <form id="bulkModalForm" method="POST" action="website_status.php" class="flex-1 overflow-y-auto p-8 pt-0 style-scrollbar">
            <input type="hidden" name="action" value="bulk_update">
            <input type="hidden" name="ids" id="bulkModalIds" value="">
            <input type="hidden" name="current_query" id="bulkModalCurrentQuery" value="">
            
            <div class="space-y-4">
                <!-- 1. 그룹 설정 변경 -->
                <div class="p-4 rounded-xl border border-[var(--border-color)] bg-slate-50/10 flex gap-3 items-start">
                    <input type="checkbox" name="chk_group" id="chkGroup" class="mt-1.5 w-4 h-4 rounded accent-[#18aee7] cursor-pointer" onchange="toggleBulkField('chkGroup', ['bulkGroupSelect', 'bulkGroupNew'])">
                    <div class="flex-1">
                        <label for="chkGroup" class="block text-xs font-black text-[var(--text-sub)] mb-1.5 cursor-pointer uppercase tracking-wider">
                            <?= $current_lang == 'KO' ? '그룹 변경' : 'Change Group' ?>
                        </label>
                        <select name="group_select" id="bulkGroupSelect" onchange="toggleBulkGroupNewInput()" class="w-full p-2.5 border border-[var(--border-color)] rounded-xl bg-[var(--bg-color)] text-[var(--text-main)] outline-none focus:border-[#18aee7] disabled:opacity-40" disabled>
                            <?php foreach($existing_groups as $g): ?><option value="<?=htmlspecialchars($g)?>"><?=htmlspecialchars($g)?></option><?php endforeach; ?>
                            <option value="_NEW_" class="text-[#18aee7] font-bold"><?= $t['group_add_new'] ?></option>
                        </select>
                        <input type="text" name="group_name_new" id="bulkGroupNew" class="hidden mt-2 w-full p-2.5 border-l-4 border-l-[#18aee7] border border-[var(--border-color)] rounded-xl bg-[var(--bg-color)] disabled:opacity-40" placeholder="새 그룹명 입력" disabled>
                    </div>
                </div>

                <!-- 2. 알람 상태 변경 -->
                <div class="p-4 rounded-xl border border-[var(--border-color)] bg-slate-50/10 flex gap-3 items-start">
                    <input type="checkbox" name="chk_check" id="chkCheck" class="mt-1.5 w-4 h-4 rounded accent-[#18aee7] cursor-pointer" onchange="toggleBulkField('chkCheck', ['bulkCheck'])">
                    <div class="flex-1">
                        <label for="chkCheck" class="block text-xs font-black text-[var(--text-sub)] mb-1.5 cursor-pointer uppercase tracking-wider">
                            <?= $current_lang == 'KO' ? '알람 상태 변경' : 'Change Alarm Status' ?>
                        </label>
                        <select name="check" id="bulkCheck" class="w-full p-2.5 border border-[var(--border-color)] rounded-xl bg-[var(--bg-color)] text-[var(--text-main)] outline-none focus:border-[#18aee7] disabled:opacity-40" disabled>
                            <option value="alarm"><?= $t['status_alarm'] ?></option>
                            <option value="no alarm"><?= $t['status_noalarm'] ?></option>
                            <option value="disable"><?= $t['status_disable'] ?></option>
                        </select>
                    </div>
                </div>

                <!-- 3. 모니터링 엔진 변경 -->
                <div class="p-4 rounded-xl border border-[var(--border-color)] bg-slate-50/10 flex gap-3 items-start">
                    <input type="checkbox" name="chk_engine" id="chkEngine" class="mt-1.5 w-4 h-4 rounded accent-[#18aee7] cursor-pointer" onchange="toggleBulkField('chkEngine', ['bulkEngineLynx', 'bulkEngineCurl'])">
                    <div class="flex-1">
                        <label for="chkEngine" class="block text-xs font-black text-[var(--text-sub)] mb-1.5 cursor-pointer uppercase tracking-wider">
                            <?= $current_lang == 'KO' ? '실행 엔진 변경' : 'Change Engine Type' ?>
                        </label>
                        <div class="flex bg-[var(--bg-color)] border border-[var(--border-color)] rounded-xl p-1 h-[42px] relative overflow-hidden" id="bulkEngineWrapper">
                            <label class="flex-1 cursor-pointer h-full">
                                <input type="radio" name="engine_type" id="bulkEngineLynx" value="lynx" class="hidden peer" checked disabled>
                                <div class="h-full flex items-center justify-center text-xs font-bold rounded-lg peer-checked:bg-[#18aee7] peer-checked:text-white peer-disabled:opacity-40 transition-all">LYNX</div>
                            </label>
                            <label class="flex-1 cursor-pointer h-full">
                                <input type="radio" name="engine_type" id="bulkEngineCurl" value="curl" class="hidden peer" disabled>
                                <div class="h-full flex items-center justify-center text-xs font-bold rounded-lg peer-checked:bg-[#18aee7] peer-checked:text-white peer-disabled:opacity-40 transition-all">CURL</div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- 4. 체크 횟수 및 타임아웃 변경 -->
                <div class="p-4 rounded-xl border border-[var(--border-color)] bg-slate-50/10 flex gap-3 items-start">
                    <input type="checkbox" name="chk_limits" id="chkLimits" class="mt-1.5 w-4 h-4 rounded accent-[#18aee7] cursor-pointer" onchange="toggleBulkField('chkLimits', ['bulkRetry', 'bulkTimeout'])">
                    <div class="flex-1 grid grid-cols-2 gap-3">
                        <div>
                            <label for="chkLimits" class="block text-xs font-black text-[var(--text-sub)] mb-1.5 cursor-pointer uppercase tracking-wider">
                                <?= $current_lang == 'KO' ? '체크 횟수 변경' : 'Change Retry Count' ?>
                            </label>
                            <select name="retry_count" id="bulkRetry" class="w-full p-2.5 border border-[var(--border-color)] rounded-xl bg-[var(--bg-color)] text-[var(--text-main)] outline-none focus:border-[#18aee7] disabled:opacity-40" disabled>
                                <option value="1">1<?= $current_lang == 'KO' ? '회 (기본)' : ' Time' ?></option>
                                <option value="2">2<?= $current_lang == 'KO' ? '회' : ' Times' ?></option>
                                <option value="3">3<?= $current_lang == 'KO' ? '회' : ' Times' ?></option>
                                <option value="5">5<?= $current_lang == 'KO' ? '회' : ' Times' ?></option>
                            </select>
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
                <div class="p-4 rounded-xl border border-[var(--border-color)] bg-slate-50/10 flex gap-3 items-start">
                    <input type="checkbox" name="chk_expiry" id="chkExpiry" class="mt-1.5 w-4 h-4 rounded accent-[#18aee7] cursor-pointer" onchange="toggleBulkField('chkExpiry', ['bulkSsl', 'bulkDom'])">
                    <div class="flex-1 grid grid-cols-2 gap-3">
                        <div>
                            <label for="chkExpiry" class="block text-xs font-black text-[var(--text-sub)] mb-1.5 cursor-pointer uppercase tracking-wider">SSL Expiry</label>
                            <select name="ssl_mon" id="bulkSsl" class="w-full p-2.5 border border-[var(--border-color)] rounded-xl bg-[var(--bg-color)] text-[var(--text-main)] outline-none focus:border-[#18aee7] disabled:opacity-40" disabled>
                                <option value="1">ON</option><option value="0">OFF</option>
                            </select>
                        </div>
                        <div>
                            <label for="chkExpiry" class="block text-xs font-black text-[var(--text-sub)] mb-1.5 cursor-pointer uppercase tracking-wider">Domain Expiry</label>
                            <select name="dom_mon" id="bulkDom" class="w-full p-2.5 border border-[var(--border-color)] rounded-xl bg-[var(--bg-color)] text-[var(--text-main)] outline-none focus:border-[#18aee7] disabled:opacity-40" disabled>
                                <option value="1">ON</option><option value="0">OFF</option>
                            </select> 
                        </div>
                    </div>
                </div>

                <!-- 6. 이메일 알림 방식 및 수/발신 주소 변경 -->
                <div class="p-4 rounded-xl border border-[var(--border-color)] bg-slate-50/10 flex gap-3 items-start">
                    <input type="checkbox" name="chk_email" id="chkEmail" class="mt-1.5 w-4 h-4 rounded accent-[#18aee7] cursor-pointer" onchange="toggleBulkField('chkEmail', ['bulkEmailMode', 'bulkSenderEmail', 'bulkReceiverEmail']); toggleBulkSmtpVisibility();">
                    <div class="flex-1 space-y-3">
                        <div>
                            <label for="chkEmail" class="block text-xs font-black text-[var(--text-sub)] mb-1.5 cursor-pointer uppercase tracking-wider">
                                <?= $current_lang == 'KO' ? '이메일 알림 설정 변경' : 'Change Email Notification' ?>
                            </label>
                            <select name="email_mode" id="bulkEmailMode" onchange="toggleBulkSmtpVisibility()" class="w-full p-2.5 border border-[var(--border-color)] rounded-xl bg-[var(--bg-color)] text-[var(--text-main)] outline-none focus:border-[#18aee7] disabled:opacity-40" disabled>
                                <option value="none"><?= $current_lang == 'KO' ? '미발송 (사용 안 함)' : 'Disabled' ?></option>
                                <option value="postfix"><?= $current_lang == 'KO' ? '로컬 Postfix 직접 발송' : 'Direct Postfix' ?></option>
                                <option value="smtp"><?= $current_lang == 'KO' ? '외부 SMTP 릴레이 발송 (기본설정 릴레이)' : 'SMTP Relay (System SMTP)' ?></option>
                            </select>
                        </div>
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

                <!-- 7. 개별 SMTP 서버 설정 변경 -->
                <div class="p-4 rounded-xl border border-[var(--border-color)] bg-slate-50/10 flex gap-3 items-start transition-all duration-300 hidden" id="bulkSmtpContainer">
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
                                <select name="smtp_secure" id="bulkSmtpSecure" class="w-full p-2 border border-[var(--border-color)] rounded-lg bg-[var(--bg-color)] text-xs text-[var(--text-main)] outline-none focus:border-[#18aee7] disabled:opacity-40" disabled>
                                    <option value="none">None</option>
                                    <option value="tls">TLS (587)</option>
                                    <option value="ssl">SSL (465)</option>
                                </select>
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
            </div>
            
            <!-- 경고 문구 및 버튼 영역 -->
            <div class="mt-8 border-t border-[var(--border-color)] pt-4 flex flex-col gap-4">
                <!-- ⚠️ 우측 하단/하단 경고문구 -->
                <div class="bg-red-50 dark:bg-rose-950/20 border border-red-200 dark:border-rose-900 rounded-xl p-4 flex gap-3 text-xs leading-relaxed text-red-600 dark:text-rose-400 shadow-sm">
                    <i class="fas fa-exclamation-triangle text-base mt-0.5 animate-pulse"></i>
                    <div>
                        <span class="font-bold block text-sm mb-1"><?= $current_lang == 'KO' ? '일괄 설정 적용 주의사항' : 'Caution for Bulk Application' ?></span>
                        <?= $current_lang == 'KO' 
                            ? '왼쪽의 체크박스가 선택된 항목만 값 변경이 진행되며, 체크되지 않은 항목은 각 모니터링 대상의 기존 설정값이 유지됩니다. 실행 후에는 이전 상태로 복구할 수 없습니다.' 
                            : 'Only settings with active checkboxes will be overwritten for the selected targets. Unchecked options will retain their current individual values. This action is irreversible.' 
                        ?>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pb-4">
                    <button type="button" onclick="closeBulkModal()" class="px-6 py-3 bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700 rounded-xl text-sm font-bold transition-all text-xs text-[var(--text-main)]"><?= $t['modal_cancel'] ?? '취소' ?></button>
                    <button type="submit" class="px-10 py-3 bg-[#18aee7] hover:bg-[#159bd0] text-white rounded-xl text-sm font-black shadow-lg hover:shadow-xl transition-all text-xs"><?= $t['modal_save'] ?? '일괄 변경 적용' ?></button>
                </div>
            </div>
        </form>
    </div>
</div>
