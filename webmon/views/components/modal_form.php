<div id="addModal" class="hidden fixed inset-0 z-[200] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
    <div class="modal-content flex flex-col w-full max-w-[500px] bg-[var(--card-bg)] rounded-2xl shadow-2xl border border-[var(--border-color)] max-h-[90vh] overflow-hidden">
        <h2 id="modalTitle" class="text-xl font-black mb-6 text-[var(--text-main)] border-b border-[var(--border-color)] pb-4 p-8"><?= $t['modal_add'] ?></h2>
        
        <form id="modalForm" method="POST" class="flex-1 overflow-y-auto p-8 pt-0 style-scrollbar">
            <input type="hidden" name="action" id="modalAction" value="add">
            <input type="hidden" name="no" id="modalNo" value="">
            <input type="hidden" name="current_query" id="modalCurrentQuery" value="">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-black text-[var(--text-sub)] mb-1 uppercase tracking-wider"><?= $t['h_group'] ?></label>
                    <select name="group_select" id="modalGroupSelect" onchange="toggleGroupNewInput()" class="w-full p-3 border border-[var(--border-color)] rounded-xl bg-[var(--bg-color)] text-[var(--text-main)] outline-none focus:border-[#18aee7]">
                        <?php foreach($existing_groups as $g): ?><option value="<?=htmlspecialchars($g)?>"><?=htmlspecialchars($g)?></option><?php endforeach; ?>
                        <option value="_NEW_" class="text-[#18aee7] font-bold"><?= $t['group_add_new'] ?></option>
                    </select>
                    <input type="text" name="group_name_new" id="modalGroupNew" class="hidden mt-2 w-full p-3 border-l-4 border-l-[#18aee7] border border-[var(--border-color)] rounded-xl bg-[var(--bg-color)]" placeholder="새 그룹명 입력">
                </div>
                <div>
                    <label class="block text-xs font-black text-[var(--text-sub)] mb-1 uppercase tracking-wider"><?= $t['h_company'] ?></label>
                    <input type="text" name="company" id="modalCompany" class="w-full p-3 border border-[var(--border-color)] rounded-xl bg-[var(--bg-color)] text-[var(--text-main)] outline-none focus:border-[#18aee7]" required>
                </div>
                <div>
                    <label class="block text-xs font-black text-[var(--text-sub)] mb-1 uppercase tracking-wider"><?= $t['h_url'] ?></label>
                    <input type="text" name="url" id="modalUrl" class="w-full p-3 border border-[var(--border-color)] rounded-xl bg-[var(--bg-color)] text-[var(--text-main)] outline-none focus:border-[#18aee7]" required>
                </div>
                <div>
                    <label class="block text-xs font-black text-[var(--text-sub)] mb-1 uppercase tracking-wider"><?= $t['h_keyword'] ?></label>
                    <input type="text" name="contents" id="modalContents" class="w-full p-3 border border-[var(--border-color)] rounded-xl bg-[var(--bg-color)] text-[var(--text-main)] outline-none focus:border-[#18aee7]" required>
                </div>
                <div>
                    <label class="block text-xs font-black text-[var(--text-sub)] mb-1 uppercase tracking-wider"><?= $t['h_comment'] ?></label>
                    <input type="text" name="comment" id="modalComment" class="w-full p-3 border border-[var(--border-color)] rounded-xl bg-[var(--bg-color)] text-[var(--text-main)] outline-none focus:border-[#18aee7]" placeholder="<?= $t['ph_comment'] ?>">
                </div>
                <div>
                    <label class="block text-xs font-black text-[var(--text-sub)] mb-1 uppercase tracking-wider"><?= $current_lang == 'KO' ? '이메일 발송 방식' : 'Email Mode' ?></label>
                    <select name="email_mode" id="modalEmailMode" onchange="toggleModalSmtpFields()" class="w-full p-3 border border-[var(--border-color)] rounded-xl bg-[var(--bg-color)] text-[var(--text-main)] outline-none focus:border-[#18aee7]">
                        <option value="none"><?= $current_lang == 'KO' ? '미발송 (사용 안 함)' : 'Disabled' ?></option>
                        <option value="postfix"><?= $current_lang == 'KO' ? '로컬 Postfix 직접 발송' : 'Direct Postfix' ?></option>
                        <option value="smtp"><?= $current_lang == 'KO' ? '외부 SMTP 릴레이 발송' : 'SMTP Relay' ?></option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-black text-[var(--text-sub)] mb-1 uppercase tracking-wider"><?= $current_lang == 'KO' ? '발송자 이메일' : 'Sender Email' ?></label>
                    <input type="email" name="sender_email" id="modalSenderEmail" class="w-full p-3 border border-[var(--border-color)] rounded-xl bg-[var(--bg-color)] text-[var(--text-main)] outline-none focus:border-[#18aee7]" placeholder="sender@example.com (선택)">
                </div>
                <div>
                    <label class="block text-xs font-black text-[var(--text-sub)] mb-1 uppercase tracking-wider"><?= $current_lang == 'KO' ? '수신자 이메일' : 'Receiver Email' ?></label>
                    <input type="email" name="receiver_email" id="modalReceiverEmail" class="w-full p-3 border border-[var(--border-color)] rounded-xl bg-[var(--bg-color)] text-[var(--text-main)] outline-none focus:border-[#18aee7]" placeholder="receiver@example.com (선택)">
                </div>

                <!-- 개별 SMTP 설정 폼 (SMTP 선택 시 동적 노출) -->
                <div id="modalSmtpFields" class="hidden space-y-4 border border-[var(--border-color)] p-4 rounded-xl bg-slate-50/20 dark:bg-slate-800/10">
                    <h5 class="text-xs font-black text-[#18aee7] uppercase tracking-wider"><i class="fas fa-server"></i> <?= $current_lang == 'KO' ? '개별 SMTP 서버 설정' : 'Individual SMTP Settings' ?></h5>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-black text-[var(--text-sub)] mb-0.5">SMTP Host</label>
                            <input type="text" name="smtp_host" id="modalSmtpHost" class="w-full p-2 border border-[var(--border-color)] rounded-lg bg-[var(--bg-color)] text-xs text-[var(--text-main)] outline-none focus:border-[#18aee7]" placeholder="smtp.gmail.com">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-[var(--text-sub)] mb-0.5">SMTP Port</label>
                            <input type="number" name="smtp_port" id="modalSmtpPort" class="w-full p-2 border border-[var(--border-color)] rounded-lg bg-[var(--bg-color)] text-xs text-[var(--text-main)] outline-none focus:border-[#18aee7]" placeholder="587" value="25">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-[var(--text-sub)] mb-0.5">Secure Mode</label>
                            <select name="smtp_secure" id="modalSmtpSecure" class="w-full p-2 border border-[var(--border-color)] rounded-lg bg-[var(--bg-color)] text-xs text-[var(--text-main)] outline-none focus:border-[#18aee7]">
                                <option value="none">None</option>
                                <option value="tls">TLS (587)</option>
                                <option value="ssl">SSL (465)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-[var(--text-sub)] mb-0.5">SMTP Password</label>
                            <input type="password" name="smtp_pass" id="modalSmtpPass" class="w-full p-2 border border-[var(--border-color)] rounded-lg bg-[var(--bg-color)] text-xs text-[var(--text-main)] outline-none focus:border-[#18aee7]" placeholder="Password">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-[10px] font-black text-[var(--text-sub)] mb-0.5">SMTP Username</label>
                            <input type="text" name="smtp_user" id="modalSmtpUser" class="w-full p-2 border border-[var(--border-color)] rounded-lg bg-[var(--bg-color)] text-xs text-[var(--text-main)] outline-none focus:border-[#18aee7]" placeholder="user@gmail.com">
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-black text-[var(--text-sub)] mb-1 uppercase tracking-wider"><?= $t['h_alarm'] ?></label>
                        <select name="check" id="modalCheck" class="w-full p-3 border border-[var(--border-color)] rounded-xl bg-[var(--bg-color)] text-[var(--text-main)]">
                            <option value="alarm"><?= $t['status_alarm'] ?></option>
                            <option value="no alarm"><?= $t['status_noalarm'] ?></option>
                            <option value="disable"><?= $t['status_disable'] ?></option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-black text-[var(--text-sub)] mb-1 uppercase tracking-wider">SSL Expiry</label>
                        <select name="ssl_mon" id="modalSsl" class="w-full p-3 border border-[var(--border-color)] rounded-xl bg-[var(--bg-color)] text-[var(--text-main)]">
                            <option value="1">ON</option><option value="0">OFF</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-black text-[var(--text-sub)] mb-1 uppercase tracking-wider">Domain Expiry</label>
                        <select name="dom_mon" id="modalDom" class="w-full p-3 border border-[var(--border-color)] rounded-xl bg-[var(--bg-color)] text-[var(--text-main)]">
                            <option value="1">ON</option><option value="0">OFF</option>
                        </select> 
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4 mt-4">
                    <div>
                        <label class="block text-xs font-black text-[var(--text-sub)] mb-2 uppercase tracking-wider"><?= $t['modal_engine'] ?? 'ENGINE' ?></label>
                        <div class="flex bg-[var(--bg-color)] border border-[var(--border-color)] rounded-xl p-1 h-[46px]">
                            <label class="flex-1 cursor-pointer h-full">
                                <input type="radio" name="engine_type" value="lynx" class="hidden peer" checked>
                                <div class="h-full flex items-center justify-center text-xs font-bold rounded-lg peer-checked:bg-[#18aee7] peer-checked:text-white transition-all">LYNX</div>
                            </label>
                            <label class="flex-1 cursor-pointer h-full">
                                <input type="radio" name="engine_type" value="curl" class="hidden peer">
                                <div class="h-full flex items-center justify-center text-xs font-bold rounded-lg peer-checked:bg-[#18aee7] peer-checked:text-white transition-all">CURL</div>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-black text-[var(--text-sub)] mb-2 uppercase tracking-wider"><?= $t['modal_retry'] ?? 'RETRY COUNT' ?></label>
                        <select name="retry_count" id="modalRetry" class="w-full h-[46px] px-3 border border-[var(--border-color)] rounded-xl bg-[var(--bg-color)] text-[var(--text-main)] outline-none focus:border-[#18aee7]">
                            <option value="1">1<?= $current_lang == 'KO' ? '회 (기본)' : ' Time' ?></option>
                            <option value="2">2<?= $current_lang == 'KO' ? '회' : ' Times' ?></option>
                            <option value="3">3<?= $current_lang == 'KO' ? '회' : ' Times' ?></option>
                            <option value="5">5<?= $current_lang == 'KO' ? '회' : ' Times' ?></option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-black text-[var(--text-sub)] mb-1 uppercase tracking-wider"><?= $t['modal_timeout'] ?? 'TIMEOUT (SEC)' ?></label>
                    <input type="number" name="timeout_sec" id="modalTimeout" class="w-full p-3 border border-[var(--border-color)] rounded-xl bg-[var(--bg-color)] text-[var(--text-main)]" value="2" min="1" max="30">
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-8 pb-4">
                <button type="button" onclick="closeModal()" class="px-6 py-3 bg-gray-100 dark:bg-slate-800 rounded-xl text-sm font-bold transition-colors"><?= $t['modal_cancel'] ?? '취소' ?></button>
                <button type="submit" class="px-10 py-3 bg-[#18aee7] text-white rounded-xl text-sm font-black shadow-lg hover:shadow-xl transition-all"><?= $t['modal_save'] ?? '저장' ?></button>
                </div>
        </form>
    </div>
</div>