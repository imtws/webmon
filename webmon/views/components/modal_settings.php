    
    <div id="settingsModal" class="side-sheet-overlay fixed inset-0 z-[1000] hidden justify-end bg-black/40">
        <div class="side-sheet-panel bg-[var(--card-bg)] border-l border-[var(--border-color)] w-full max-w-xl h-full shadow-2xl flex flex-col overflow-hidden" role="dialog" aria-modal="true">
            <div class="sheet-header">
                <h3 class="sheet-header-title"><i class="fas fa-sliders"></i><span><?= $current_lang == 'KO' ? '모니터링 프로그램 환경 설정' : 'System Configuration' ?></span></h3>
                <button type="button" class="sheet-close-button" aria-label="<?= $current_lang == 'KO' ? '닫기' : 'Close' ?>" onclick="closeSettingsModal()"><i class="fas fa-times"></i></button>
            </div>

            <!-- 탭 버튼 헤더 -->
            <div class="settings-tabs flex">
                <button type="button" onclick="switchSettingsTab('sys')" id="tabBtn-sys" class="flex-1 py-3 text-xs font-bold border-b-2 border-[#18aee7] text-[#18aee7] focus:outline-none">
                    <?= $current_lang == 'KO' ? '시스템 연동 설정' : 'System Config' ?>
                </button>
                <button type="button" onclick="switchSettingsTab('def')" id="tabBtn-def" class="flex-1 py-3 text-xs font-bold border-b-2 border-transparent text-[var(--text-sub)] hover:text-[var(--text-main)] focus:outline-none">
                    <?= $current_lang == 'KO' ? '신규 항목 기본값 설정' : 'Default Monitoring Values' ?>
                </button>
            </div>
            
            <form action="save_settings.php" method="POST" class="sheet-form flex-1 overflow-y-auto p-5 pb-0 flex flex-col gap-4 text-sm">
                <!-- 탭 1: 시스템 설정 -->
                <div id="tabContent-sys" class="flex flex-col gap-4">
                    <!-- 1. Telegram settings -->
                    <div class="settings-accordion">
                        <div class="settings-accordion-header">
                            <button type="button" onclick="toggleSettingsAccordion('tg')" class="settings-accordion-trigger">
                                <span class="flex items-center gap-2"><i class="fab fa-telegram"></i> <?= $current_lang == 'KO' ? '텔레그램 알림 설정' : 'Telegram Alert Settings' ?></span>
                                <i id="set-arrow-tg" class="fas fa-chevron-down text-xs text-[var(--text-sub)] transition-transform duration-200"></i>
                            </button>
                            <label class="settings-channel-toggle">
                                <span><?= $current_lang == 'KO' ? '텔레그램 알림' : 'Telegram alerts' ?></span>
                                <input type="checkbox" name="tg_enabled" value="1" class="sr-only peer" aria-label="<?= $current_lang == 'KO' ? '텔레그램 알림 사용' : 'Enable Telegram alerts' ?>" <?= ($settings['tg_enabled'] ?? '0') == '1' ? 'checked' : '' ?>>
                                <span class="settings-toggle-track"><span></span></span>
                            </label>
                        </div>
                        <div id="set-body-tg" class="p-4 border-t border-[var(--border-color)] hidden">
                            <p class="mb-3 text-[11px] text-[var(--text-sub)]"><?= $current_lang == 'KO' ? '모니터링 장애 알림을 Telegram Bot으로 보냅니다. 토큰과 Chat ID를 입력하고 저장한 다음 알림을 켜세요.' : 'Send monitoring alerts through a Telegram bot. Enter the bot token and chat ID, save the settings, then enable alerts.' ?></p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1">Bot Token</label>
                                    <input type="password" name="tg_token" value="<?= htmlspecialchars($settings['tg_token'] ?? '') ?>" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]" placeholder="<?= $current_lang == 'KO' ? 'Bot 토큰 입력' : 'Enter bot token' ?>">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1">Chat ID</label>
                                    <input type="text" name="tg_chat_id" value="<?= htmlspecialchars($settings['tg_chat_id'] ?? '') ?>" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]" placeholder="<?= $current_lang == 'KO' ? 'Chat ID 입력' : 'Enter chat ID' ?>">
                                </div>
                            </div>
                            <div class="mt-4 flex flex-wrap items-center gap-3">
                                <button type="button" onclick="testNotification('telegram', this)" class="rounded-lg border border-[var(--border-color)] bg-[var(--card-bg)] px-3 py-2 text-xs font-bold text-[var(--text-main)] hover:border-[#18aee7] hover:text-[#18aee7]">
                                    <i class="fab fa-telegram mr-1"></i><?= $current_lang == 'KO' ? '텔레그램 테스트 발송' : 'Send Telegram Test' ?>
                                </button>
                                <span id="telegramTestStatus" class="text-xs" role="status" aria-live="polite"></span>
                            </div>
                            <p class="mt-2 text-[10px] text-[var(--text-sub)]"><?= $current_lang == 'KO' ? '저장하지 않은 토큰과 Chat ID로 테스트 메시지를 한 번 보냅니다.' : 'Sends one test message using the current values without saving them.' ?></p>
                        </div>
                    </div>

                    <!-- 2. 이메일 발송 설정 -->
                    <div class="settings-accordion">
                        <div class="settings-accordion-header">
                            <button type="button" onclick="toggleSettingsAccordion('email')" class="settings-accordion-trigger">
                                <span class="flex items-center gap-2"><i class="fas fa-paper-plane"></i> <?= $current_lang == 'KO' ? '이메일 발송 설정' : 'Email Sending Settings' ?></span>
                                <i id="set-arrow-email" class="fas fa-chevron-down text-xs text-[var(--text-sub)] transition-transform duration-200"></i>
                            </button>
                            <label class="settings-channel-toggle">
                                <span><?= $current_lang == 'KO' ? '이메일 알림' : 'Email alerts' ?></span>
                                <input type="checkbox" name="email_enabled" value="1" class="sr-only peer" aria-label="<?= $current_lang == 'KO' ? '이메일 알림 사용' : 'Enable email alerts' ?>" <?= ($settings['email_enabled'] ?? '0') == '1' ? 'checked' : '' ?>>
                                <span class="settings-toggle-track"><span></span></span>
                            </label>
                        </div>
                        <div id="set-body-email" class="p-4 border-t border-[var(--border-color)] hidden">
                            <p class="text-[11px] text-[var(--text-sub)] mb-3"><?= $current_lang == 'KO' ? '이 주소가 메일의 From 및 SMTP 봉투 발신자로 사용됩니다. 모니터링 항목에 별도 발송자 주소가 있으면 해당 주소가 우선합니다. 릴레이를 사용하지 않으면 수신 도메인의 메일 서버로 직접 전달하며, 최종 수신 여부는 서버의 25번 포트 연결과 발신 도메인 메일 정책에 따라 달라집니다.' : 'This address is used for both the From header and SMTP envelope sender. An item-specific sender takes precedence. Without relay, mail is delivered directly to the recipient domain MX; final delivery depends on outbound port 25 connectivity and the sender domain mail policy.' ?></p>
                            <div>
                                <label for="globalSenderEmail" class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '기본 발송 이메일 주소 (From)' : 'Default sender email (From)' ?></label>
                                <input type="email" name="global_sender_email" id="globalSenderEmail" value="<?= htmlspecialchars($settings['global_sender_email'] ?? '') ?>" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]" placeholder="webmon@example.com">
                            </div>
                            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                                <div>
                                    <label for="emailTestRecipient" class="mb-1 block text-xs font-bold text-[var(--text-sub)]"><?= $current_lang == 'KO' ? '테스트 수신 주소 (To)' : 'Test recipient (To)' ?></label>
                                    <input type="email" id="emailTestRecipient" class="w-full rounded-lg border border-[var(--border-color)] bg-[var(--bg-color)] p-2 text-xs outline-none focus:border-[#18aee7]" placeholder="you@example.com" autocomplete="email">
                                </div>
                                <div class="flex items-center">
                                    <button type="button" onclick="testNotification('email', this)" class="shrink-0 rounded-lg border border-[var(--border-color)] bg-[var(--card-bg)] px-3 py-2 text-xs font-bold text-[var(--text-main)] hover:border-[#18aee7] hover:text-[#18aee7]">
                                        <i class="fas fa-paper-plane mr-1"></i><?= $current_lang == 'KO' ? '이메일 테스트 발송' : 'Send Email Test' ?>
                                    </button>
                                </div>
                            </div>
                            <p id="emailTestStatus" class="mt-2 text-xs" role="status" aria-live="polite"></p>
                            <p class="mt-1 text-[10px] text-[var(--text-sub)]"><?= $current_lang == 'KO' ? '테스트는 저장된 릴레이 사용 설정에 따라 직접 발송 또는 전역 SMTP 릴레이로 발송됩니다. 설정을 먼저 저장해 주세요. 서버 접수는 최종 수신 완료를 의미하지 않습니다.' : 'The test uses direct delivery or the global SMTP relay according to the saved setting. Save settings first. Server acceptance does not confirm final delivery.' ?></p>
                        </div>
                    </div>

                    <!-- 3. SMTP 릴레이 서버 설정 -->
                    <div class="settings-accordion">
                        <div class="settings-accordion-header">
                            <button type="button" onclick="toggleSettingsAccordion('smtp')" class="settings-accordion-trigger">
                                <span class="flex items-center gap-2"><i class="fas fa-mail-bulk"></i> <?= $current_lang == 'KO' ? '전역 SMTP 릴레이 서버 설정' : 'Global SMTP Relay Settings' ?></span>
                                <i id="set-arrow-smtp" class="fas fa-chevron-down text-xs text-[var(--text-sub)] transition-transform duration-200"></i>
                            </button>
                            <label class="settings-channel-toggle">
                                <span><?= $current_lang == 'KO' ? '릴레이 사용' : 'Use relay' ?></span>
                                <input type="checkbox" name="smtp_relay_enabled" value="1" class="sr-only peer" aria-label="<?= $current_lang == 'KO' ? '전역 SMTP 릴레이 사용' : 'Enable global SMTP relay' ?>" <?= ($settings['smtp_mode'] ?? 'direct') === 'relay' ? 'checked' : '' ?>>
                                <span class="settings-toggle-track"><span></span></span>
                            </label>
                        </div>
                        <div id="set-body-smtp" class="p-4 border-t border-[var(--border-color)] hidden">
                            <p class="text-[11px] text-[var(--text-sub)] mb-3"><?= $current_lang == 'KO' ? '릴레이를 켜면 직접 발송 방식으로 설정된 모니터링 메일이 아래 SMTP 서버와 계정으로 발송됩니다. 끄면 수신 도메인의 메일 서버로 직접 발송합니다. 모니터링 항목별 SMTP 설정은 이 전역 설정과 별도로 동작합니다.' : 'When enabled, monitor emails using direct delivery mode are sent through the SMTP server and credentials below. When disabled, mail is delivered directly to recipient mail servers. Per-monitor SMTP settings are independent of this global setting.' ?></p>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1">SMTP Host</label>
                                    <input type="text" name="smtp_host" value="<?= htmlspecialchars($settings['smtp_host'] ?? 'mail') ?>" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]" placeholder="smtp.gmail.com">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1">SMTP Port</label>
                                    <input type="text" name="smtp_port" value="<?= htmlspecialchars($settings['smtp_port'] ?? '25') ?>" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]" placeholder="587">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1">Secure Mode</label>
                                    <select name="smtp_secure" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]">
                                        <option value="none" <?= ($settings['smtp_secure'] ?? 'none') == 'none' ? 'selected' : '' ?>>None</option>
                                        <option value="tls" <?= ($settings['smtp_secure'] ?? 'none') == 'tls' ? 'selected' : '' ?>>TLS (587)</option>
                                        <option value="ssl" <?= ($settings['smtp_secure'] ?? 'none') == 'ssl' ? 'selected' : '' ?>>SSL (465)</option>
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1">SMTP Username</label>
                                    <input type="text" name="smtp_user" value="<?= htmlspecialchars($settings['smtp_user'] ?? '') ?>" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]" placeholder="user@gmail.com">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1">SMTP Password</label>
                                    <input type="password" name="smtp_pass" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]" placeholder="••••••••">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. 알림 발송 포맷 및 템플릿 설정 -->
                    <div class="settings-accordion">
                        <button type="button" onclick="toggleSettingsAccordion('template')" class="w-full flex justify-between items-center p-4 font-bold text-left focus:outline-none select-none hover:bg-slate-100/50 dark:hover:bg-slate-800/50 transition-colors">
                            <span class="flex items-center gap-2 text-indigo-500"><i class="fas fa-envelope-open-text"></i> <?= $current_lang == 'KO' ? '알림 발송 포맷 및 템플릿 설정' : 'Alert Format & Template' ?></span>
                            <i id="set-arrow-template" class="fas fa-chevron-down text-xs text-[var(--text-sub)] transition-transform duration-200"></i>
                        </button>
                        <div id="set-body-template" class="p-4 border-t border-[var(--border-color)] hidden">
                            <div class="mb-4">
                                <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '알림 포맷 방식' : 'Alert Format' ?></label>
                                <select name="alert_format" id="alertFormatSelect" onchange="toggleAlertFormatTemplates()" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]">
                                    <option value="plain" <?= ($settings['alert_format'] ?? 'plain') == 'plain' ? 'selected' : '' ?>><?= $current_lang == 'KO' ? 'Plain Text (일반 텍스트)' : 'Plain Text' ?></option>
                                    <option value="html" <?= ($settings['alert_format'] ?? 'plain') == 'html' ? 'selected' : '' ?>><?= $current_lang == 'KO' ? 'HTML (서식 있는 템플릿)' : 'HTML (Formatted Template)' ?></option>
                                </select>
                            </div>

                            <div class="mb-4">
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '이메일 제목' : 'Email Subject' ?></label>
                                    <input type="text" name="email_subject" maxlength="200" value="<?= htmlspecialchars($settings['email_subject'] ?? '[WebSiteMonitor] 웹사이트 모니터링 알람 발생 {YYYY-MM-DD}') ?>" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]">
                                    <p class="mt-1 text-[10px] text-[var(--text-sub)]"><?= $current_lang == 'KO' ? '{YYYY-MM-DD}는 발생 날짜, {time}은 발생 일시로 치환됩니다.' : '{YYYY-MM-DD} is replaced with the alert date and {time} with the alert date and time.' ?></p>
                                </div>


                            <!-- HTML 템플릿 작성 영역 (HTML 모드 시 노출) -->
                            <div id="htmlTemplateFields" class="hidden space-y-4">
                                <div class="p-3 bg-blue-50/50 dark:bg-slate-800/40 border border-blue-200 dark:border-slate-700 rounded-lg text-xs">
                                    <h5 class="font-bold text-[#18aee7] mb-1"><i class="fas fa-info-circle"></i> <?= $current_lang == 'KO' ? '사용 가능한 변수 안내 (HTML)' : 'Available Variables (HTML)' ?></h5>
                                    <p class="text-[11px] text-[var(--text-sub)] leading-relaxed">
                                        <?= $current_lang == 'KO' ? '아래 HTML 템플릿 내에 변수를 삽입하면 알림 발송 시 실제 값으로 자동 치환됩니다:' : 'Insert variables into the HTML templates below to replace with actual values:' ?><br>
                                        <span class="font-mono text-xs text-orange-500 block">
                                            <?= $current_lang == 'KO' ? '공통' : 'Common' ?>: {time} {YYYY-MM-DD} {web_url} {problem_count} {recovery_count}<br>
                                            Problem: {no} {company} {url} {keyword} {cause} {detail} {engine} {response_time} {comment}<br>
                                            Recovery: {no} {company} {url} {since} {at} {duration}
                                        </span>
                                        <span class="block mt-1">
                                            <?= $current_lang == 'KO' ? '반복 구간: {#problems}...{/problems}, {#recoveries}...{/recoveries} / 조건 구간: {#has_problems}, {#has_recoveries}, {#comment}(권장 처리가 있을 때만), {#duration}(지속 시간이 있을 때만) ... {/이름}으로 닫습니다.' : 'Repeat blocks: {#problems}...{/problems}, {#recoveries}...{/recoveries}. Conditional blocks: {#has_problems}, {#has_recoveries}, {#comment} (only when an action guide exists), {#duration}. Close each with {/name}.' ?>
                                        </span>
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '이메일 HTML 템플릿' : 'Email HTML Template' ?></label>
                                    <textarea name="email_template_html" rows="14" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs font-mono outline-none focus:border-[#18aee7]" placeholder="<?= $current_lang == 'KO' ? 'HTML 코드를 입력해 주세요...' : 'Enter HTML template...' ?>"><?= htmlspecialchars($settings['email_template_html'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <!-- Plain Text 템플릿 작성 영역 (Plain 모드 시 노출) -->
                            <div id="plainTemplateFields" class="hidden space-y-4">
                                <div class="p-3 bg-blue-50/50 dark:bg-slate-800/40 border border-blue-200 dark:border-slate-700 rounded-lg text-xs">
                                    <h5 class="font-bold text-[#18aee7] mb-1"><i class="fas fa-info-circle"></i> <?= $current_lang == 'KO' ? '사용 가능한 변수 안내 (Plain Text)' : 'Available Variables (Plain Text)' ?></h5>
                                    <p class="text-[11px] text-[var(--text-sub)] leading-relaxed">
                                        <?= $current_lang == 'KO' ? '아래 일반 텍스트 템플릿 내에 변수를 삽입하면 알림 발송 시 실제 값으로 자동 치환됩니다:' : 'Insert variables into the plain text templates below to replace with actual values:' ?><br>
                                        <span class="font-mono text-xs text-orange-500 block">
                                            <?= $current_lang == 'KO' ? '공통' : 'Common' ?>: {time} {YYYY-MM-DD} {web_url} {problem_count} {recovery_count}<br>
                                            Problem: {no} {company} {url} {keyword} {cause} {detail} {engine} {response_time} {comment}<br>
                                            Recovery: {no} {company} {url} {since} {at} {duration}
                                        </span>
                                        <span class="block mt-1">
                                            <?= $current_lang == 'KO' ? '반복 구간: {#problems}...{/problems}, {#recoveries}...{/recoveries} / 조건 구간: {#has_problems}, {#has_recoveries}, {#comment}(권장 처리가 있을 때만), {#duration}(지속 시간이 있을 때만) ... {/이름}으로 닫습니다.' : 'Repeat blocks: {#problems}...{/problems}, {#recoveries}...{/recoveries}. Conditional blocks: {#has_problems}, {#has_recoveries}, {#comment} (only when an action guide exists), {#duration}. Close each with {/name}.' ?>
                                        </span>
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '이메일 Plain Text 템플릿' : 'Email Plain Text Template' ?></label>
                                    <textarea name="email_template_plain" rows="12" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs font-mono outline-none focus:border-[#18aee7]" placeholder="<?= $current_lang == 'KO' ? '일반 텍스트 형식의 템플릿을 입력해 주세요...' : 'Enter template in plain text format...' ?>"><?= htmlspecialchars($settings['email_template_plain'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <!-- Telegram messages are sent as plain text. -->
                            <div class="mt-4">
                                <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '텔레그램 일반 텍스트 템플릿' : 'Telegram Plain Text Template' ?></label>
                                <p class="mb-2 text-[10px] text-[var(--text-sub)]"><?= $current_lang == 'KO' ? '같은 점검 주기의 장애·복구를 한 메시지로 묶어 보냅니다. 사용 가능한 변수와 구간은 위 안내와 같습니다.' : 'Failures and recoveries from the same check cycle are sent as one message. Variables and blocks are the same as above.' ?></p>
                                <textarea name="tg_template_plain" rows="12" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs font-mono outline-none focus:border-[#18aee7]" placeholder="<?= $current_lang == 'KO' ? '일반 텍스트 템플릿을 입력해 주세요...' : 'Enter a plain text template...' ?>"><?= htmlspecialchars($settings['tg_template_plain'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- SSL/도메인 만료 안내 -->
                    <?php $reportWeekdays = array_filter(explode(',', $settings['report_weekdays'] ?? '1'), 'strlen'); ?>
                    <div class="settings-accordion">
                        <div class="settings-accordion-header">
                            <button type="button" onclick="toggleSettingsAccordion('report')" class="settings-accordion-trigger">
                                <span class="flex items-center gap-2"><i class="fas fa-calendar-check"></i> <?= $current_lang == 'KO' ? 'SSL·도메인 만료 안내' : 'SSL & Domain Expiry Report' ?></span>
                                <i id="set-arrow-report" class="fas fa-chevron-down text-xs text-[var(--text-sub)] transition-transform duration-200"></i>
                            </button>
                            <label class="settings-channel-toggle">
                                <span><?= $current_lang == 'KO' ? '만료 안내 발송' : 'Expiry reports' ?></span>
                                <input type="checkbox" name="report_enabled" value="1" class="sr-only peer" aria-label="<?= $current_lang == 'KO' ? '만료 안내 발송' : 'Expiry reports' ?>" <?= ($settings['report_enabled'] ?? '1') == '1' ? 'checked' : '' ?>>
                                <span class="settings-toggle-track"><span></span></span>
                            </label>
                        </div>
                        <div id="set-body-report" class="p-4 border-t border-[var(--border-color)] hidden">
                            <p class="mb-3 text-[11px] text-[var(--text-sub)]"><?= $current_lang == 'KO' ? '설정한 요일과 시간에 SSL 인증서와 도메인이 곧 만료되는 항목을 이메일로 안내합니다. 만료일 갱신은 매일 01:00에 자동으로 실행됩니다.' : 'On the chosen days and time, expiring SSL certificates and domains are sent by email. Expiry dates are refreshed automatically every day at 01:00.' ?></p>
                            <div class="mb-4">
                                <label class="block text-xs font-bold text-[var(--text-sub)] mb-2"><?= $current_lang == 'KO' ? '발송 요일' : 'Send on' ?></label>
                                <div class="flex flex-wrap gap-2">
                                    <label class="inline-flex items-center"><input type="checkbox" name="report_weekdays[]" value="1" class="sr-only peer" <?= in_array('1', $reportWeekdays, true) ? 'checked' : '' ?>><span class="px-3 py-1.5 rounded-lg border border-[var(--border-color)] text-xs font-bold cursor-pointer select-none text-[var(--text-sub)] peer-checked:bg-[#18aee7] peer-checked:border-[#18aee7] peer-checked:text-white transition-colors"><?= $current_lang == 'KO' ? '월' : 'Mon' ?></span></label>
                                    <label class="inline-flex items-center"><input type="checkbox" name="report_weekdays[]" value="2" class="sr-only peer" <?= in_array('2', $reportWeekdays, true) ? 'checked' : '' ?>><span class="px-3 py-1.5 rounded-lg border border-[var(--border-color)] text-xs font-bold cursor-pointer select-none text-[var(--text-sub)] peer-checked:bg-[#18aee7] peer-checked:border-[#18aee7] peer-checked:text-white transition-colors"><?= $current_lang == 'KO' ? '화' : 'Tue' ?></span></label>
                                    <label class="inline-flex items-center"><input type="checkbox" name="report_weekdays[]" value="3" class="sr-only peer" <?= in_array('3', $reportWeekdays, true) ? 'checked' : '' ?>><span class="px-3 py-1.5 rounded-lg border border-[var(--border-color)] text-xs font-bold cursor-pointer select-none text-[var(--text-sub)] peer-checked:bg-[#18aee7] peer-checked:border-[#18aee7] peer-checked:text-white transition-colors"><?= $current_lang == 'KO' ? '수' : 'Wed' ?></span></label>
                                    <label class="inline-flex items-center"><input type="checkbox" name="report_weekdays[]" value="4" class="sr-only peer" <?= in_array('4', $reportWeekdays, true) ? 'checked' : '' ?>><span class="px-3 py-1.5 rounded-lg border border-[var(--border-color)] text-xs font-bold cursor-pointer select-none text-[var(--text-sub)] peer-checked:bg-[#18aee7] peer-checked:border-[#18aee7] peer-checked:text-white transition-colors"><?= $current_lang == 'KO' ? '목' : 'Thu' ?></span></label>
                                    <label class="inline-flex items-center"><input type="checkbox" name="report_weekdays[]" value="5" class="sr-only peer" <?= in_array('5', $reportWeekdays, true) ? 'checked' : '' ?>><span class="px-3 py-1.5 rounded-lg border border-[var(--border-color)] text-xs font-bold cursor-pointer select-none text-[var(--text-sub)] peer-checked:bg-[#18aee7] peer-checked:border-[#18aee7] peer-checked:text-white transition-colors"><?= $current_lang == 'KO' ? '금' : 'Fri' ?></span></label>
                                    <label class="inline-flex items-center"><input type="checkbox" name="report_weekdays[]" value="6" class="sr-only peer" <?= in_array('6', $reportWeekdays, true) ? 'checked' : '' ?>><span class="px-3 py-1.5 rounded-lg border border-[var(--border-color)] text-xs font-bold cursor-pointer select-none text-[var(--text-sub)] peer-checked:bg-[#18aee7] peer-checked:border-[#18aee7] peer-checked:text-white transition-colors"><?= $current_lang == 'KO' ? '토' : 'Sat' ?></span></label>
                                    <label class="inline-flex items-center"><input type="checkbox" name="report_weekdays[]" value="0" class="sr-only peer" <?= in_array('0', $reportWeekdays, true) ? 'checked' : '' ?>><span class="px-3 py-1.5 rounded-lg border border-[var(--border-color)] text-xs font-bold cursor-pointer select-none text-[var(--text-sub)] peer-checked:bg-[#18aee7] peer-checked:border-[#18aee7] peer-checked:text-white transition-colors"><?= $current_lang == 'KO' ? '일' : 'Sun' ?></span></label>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '발송 시각' : 'Send time' ?></label>
                                    <input type="time" name="report_time" value="<?= htmlspecialchars($settings['report_time'] ?? '09:00') ?>" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '만료 임박 기준(일)' : 'Expiring within (days)' ?></label>
                                    <input type="number" name="report_within_days" min="1" max="365" value="<?= (int)($settings['report_within_days'] ?? 30) ?>" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '수신 이메일' : 'Recipient email' ?></label>
                                    <input type="email" name="report_receiver_email" value="<?= htmlspecialchars($settings['report_receiver_email'] ?? '') ?>" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]" placeholder="alert@example.com">
                                </div>
                            </div>
                            <p class="mt-2 text-[10px] text-[var(--text-sub)]"><?= $current_lang == 'KO' ? '수신 이메일을 비우면 항목별 이메일 설정의 수신자에게 발송합니다. 발송 방식은 이메일 발송 설정을 따릅니다.' : 'If empty, reports go to the recipient configured on each monitor. Delivery follows the email settings.' ?></p>
                        </div>
                    </div>

                    <!-- 5. 시스템 환경 설정 -->
                    <div class="settings-accordion">
                        <button type="button" onclick="toggleSettingsAccordion('sysenv')" class="w-full flex justify-between items-center p-4 font-bold text-left focus:outline-none select-none hover:bg-slate-100/50 dark:hover:bg-slate-800/50 transition-colors">
                            <span class="flex items-center gap-2 text-[var(--text-sub)]"><i class="fas fa-hdd"></i> <?= $current_lang == 'KO' ? '시스템 환경 설정' : 'System Environment' ?></span>
                            <i id="set-arrow-sysenv" class="fas fa-chevron-down text-xs text-[var(--text-sub)] transition-transform duration-200"></i>
                        </button>
                        <div id="set-body-sysenv" class="p-4 border-t border-[var(--border-color)] hidden">
                            <p class="text-[11px] text-[var(--text-sub)] mb-3"><?= $current_lang == 'KO' ? 'DB 실행 이력의 보관 기간과 화면 페이지 크기, 스케줄러 요약 로그의 파일 크기 및 대시보드 주소를 설정합니다.' : 'Configure database log retention and page size, scheduler summary-log file size, and dashboard URL.' ?></p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1.5"><?= $current_lang == 'KO' ? '로그 보존 기간 (일)' : 'Log Retention (Days)' ?></label>
                                    <input type="number" name="retention_days" min="1" max="365" value="<?= htmlspecialchars($settings['retention_days'] ?? '30') ?>" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1.5"><?= $current_lang == 'KO' ? '웹 대시보드 URL (알림 링크용)' : 'Dashboard URL' ?></label>
                                    <input type="text" name="webmon_url" value="<?= htmlspecialchars($settings['webmon_url'] ?? '') ?>" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]" placeholder="http://your-server-ip:8080">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1.5"><?= $current_lang == 'KO' ? '로그 페이지당 조회 건수 (20–100)' : 'Rows per log page (20–100)' ?></label>
                                    <input type="number" name="log_max_lines" min="20" max="100" value="<?= min(100, max(20, (int)($settings['log_max_lines'] ?? 100))) ?>" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1.5"><?= $current_lang == 'KO' ? '엔진 로그 파일당 크기 (MB, 최대 4개 보관)' : 'Engine log file size (MB, max 4 backups)' ?></label>
                                    <input type="number" name="log_rotate_mb" min="1" max="1000" value="<?= htmlspecialchars($settings['log_rotate_mb'] ?? '50') ?>" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1.5"><?= $current_lang == 'KO' ? '동시 실행 상한 (5–200)' : 'Concurrency limit (5–200)' ?></label>
                                    <input type="number" name="alert_concurrency" min="5" max="200" value="<?= min(200, max(5, (int)($settings['alert_concurrency'] ?? 60))) ?>" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 탭 2: 신규 등록 기본값 설정 -->
                <div id="tabContent-def" class="hidden flex flex-col gap-4">
                    <!-- 1. 모니터링 항목 기본값 -->
                    <div class="settings-accordion">
                        <button type="button" onclick="toggleSettingsAccordion('defmon')" class="w-full flex justify-between items-center p-4 font-bold text-left focus:outline-none select-none hover:bg-slate-100/50 dark:hover:bg-slate-800/50 transition-colors">
                            <span class="flex items-center gap-2 text-sky-500"><i class="fas fa-sliders-h"></i> <?= $current_lang == 'KO' ? '모니터링 항목 기본값' : 'Default Monitoring Settings' ?></span>
                            <i id="set-arrow-defmon" class="fas fa-chevron-down text-xs text-[var(--text-sub)] transition-transform duration-200"></i>
                        </button>
                        <div id="set-body-defmon" class="p-4 border-t border-[var(--border-color)] hidden">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- 실행 엔진 -->
                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '기본 실행 엔진' : 'Default Engine' ?></label>
                                    <select name="def_engine_type" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]">
                                        <option value="lynx" <?= ($settings['def_engine_type'] ?? 'lynx') == 'lynx' ? 'selected' : '' ?>>LYNX (<?= $current_lang == 'KO' ? '텍스트 추출 방식' : 'text extraction' ?>)</option>
                                        <option value="curl" <?= ($settings['def_engine_type'] ?? 'lynx') == 'curl' ? 'selected' : '' ?>>CURL (<?= $current_lang == 'KO' ? 'Raw HTML 방식' : 'raw HTML' ?>)</option>
                                    </select>
                                </div>

                                <!-- 알람 상태 -->
                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '기본 알람 상태' : 'Default Status' ?></label>
                                    <select name="def_check" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]">
                                        <option value="alarm" <?= ($settings['def_check'] ?? 'alarm') == 'alarm' ? 'selected' : '' ?>><?= $t['status_alarm'] ?? '활성화' ?></option>
                                        <option value="no alarm" <?= ($settings['def_check'] ?? 'alarm') == 'no alarm' ? 'selected' : '' ?>><?= $t['status_noalarm'] ?? '무음' ?></option>
                                        <option value="disable" <?= ($settings['def_check'] ?? 'alarm') == 'disable' ? 'selected' : '' ?>><?= $t['status_disable'] ?? '비활성화' ?></option>
                                    </select>
                                </div>

                                <!-- 체크 횟수 및 타임아웃 -->
                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '기본 체크 횟수' : 'Default Retry' ?></label>
                                    <input type="number" name="def_retry_count" min="1" max="10" value="<?= htmlspecialchars($settings['def_retry_count'] ?? '1') ?>" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '기본 타임아웃 (초)' : 'Default Timeout (sec)' ?></label>
                                    <input type="number" name="def_timeout_sec" min="1" max="30" value="<?= htmlspecialchars($settings['def_timeout_sec'] ?? '2') ?>" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]">
                                </div>

                                <!-- 기본 무음 설정 예약 시간 (def_mute_hours) -->
                                <div class="col-span-1 md:col-span-2">
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '기본 무음 설정 예약 시간 (시간 단위)' : 'Default Mute Duration (Hours)' ?></label>
                                    <input type="number" name="def_mute_hours" min="0" max="720" value="<?= htmlspecialchars($settings['def_mute_hours'] ?? '2') ?>" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]">
                                </div>

                                <!-- SSL, DOMAIN 체크박스 -->
                                <div class="col-span-1 md:col-span-2 flex gap-6 py-2 border-t border-dashed border-[var(--border-color)] mt-2">
                                    <label class="flex items-center gap-2 cursor-pointer font-bold text-xs text-[var(--text-sub)]">
                                        <input type="checkbox" name="def_ssl_mon" value="1" class="w-4 h-4 rounded accent-[#18aee7]" <?= ($settings['def_ssl_mon'] ?? '1') == '1' ? 'checked' : '' ?>>
                                        <span><?= $current_lang == 'KO' ? 'SSL 만료 감지 기본 활성화' : 'Enable SSL expiry monitoring by default' ?></span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer font-bold text-xs text-[var(--text-sub)]">
                                        <input type="checkbox" name="def_dom_mon" value="1" class="w-4 h-4 rounded accent-[#18aee7]" <?= ($settings['def_dom_mon'] ?? '1') == '1' ? 'checked' : '' ?>>
                                        <span><?= $current_lang == 'KO' ? '도메인 만료 감지 기본 활성화' : 'Enable domain expiry monitoring by default' ?></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. 기본 이메일 알림 설정 -->
                    <div class="settings-accordion">
                        <button type="button" onclick="toggleSettingsAccordion('defmail')" class="w-full flex justify-between items-center p-4 font-bold text-left focus:outline-none select-none hover:bg-slate-100/50 dark:hover:bg-slate-800/50 transition-colors">
                            <span class="flex items-center gap-2 text-[var(--text-sub)]"><i class="fas fa-envelope"></i> <?= $current_lang == 'KO' ? '기본 이메일 알림 설정' : 'Default Email Alerts Settings' ?></span>
                            <i id="set-arrow-defmail" class="fas fa-chevron-down text-xs text-[var(--text-sub)] transition-transform duration-200"></i>
                        </button>
                        <div id="set-body-defmail" class="p-4 border-t border-[var(--border-color)] hidden">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '기본 발송 방식' : 'Default Email Mode' ?></label>
                                    <select name="def_email_mode" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]">
                                        <option value="none" <?= ($settings['def_email_mode'] ?? 'none') == 'none' ? 'selected' : '' ?>><?= $current_lang == 'KO' ? '미발송' : 'Disabled' ?></option>
                                        <option value="postfix" <?= ($settings['def_email_mode'] ?? 'none') == 'postfix' ? 'selected' : '' ?>><?= $current_lang == 'KO' ? '수신 서버로 직접 발송' : 'Direct delivery' ?></option>
                                        <option value="smtp" <?= ($settings['def_email_mode'] ?? 'none') == 'smtp' ? 'selected' : '' ?>><?= $current_lang == 'KO' ? '외부 SMTP 릴레이' : 'External SMTP relay' ?></option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '기본 발송자 주소 (From)' : 'Default From Email' ?></label>
                                    <input type="email" name="def_sender_email" value="<?= htmlspecialchars($settings['def_sender_email'] ?? '') ?>" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]" placeholder="sender@example.com">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '기본 수신자 주소 (To)' : 'Default To Email' ?></label>
                                    <input type="email" name="def_receiver_email" value="<?= htmlspecialchars($settings['def_receiver_email'] ?? '') ?>" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]" placeholder="receiver@example.com">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sheet-footer -mx-5 mt-4">
                    <button type="submit" class="px-6 py-2.5 bg-[#18aee7] hover:bg-[#159bd0] text-white rounded-xl font-bold shadow-md transition-all text-xs"><?= $current_lang == 'KO' ? '설정 저장' : 'Save Settings' ?></button>
                </div>
            </form>
        </div>
    </div>
        </div>
    </div>

