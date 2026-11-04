/**
 * 검색 및 필터 통합 실행
 */
function triggerSearch() {
    const scElem = document.getElementById('searchCompany');
    const sgElem = document.getElementById('searchGroup');
    const fsElem = document.getElementById('filterStatus');
    const feElem = document.getElementById('filterEngine');
    const soElem = document.getElementById('sortOrder');
    
    const sc = scElem ? scElem.value : '';
    const sg = sgElem ? sgElem.value : '';
    const fs = fsElem ? fsElem.value : '';
    const fe = feElem ? feElem.value : 'all';
    const so = soElem ? soElem.value : '';
    
    const url = new URL(window.location.href);
    url.searchParams.set('search_company', sc);
    url.searchParams.set('search_group', sg);
    url.searchParams.set('filter_status', fs);
    url.searchParams.set('filter_engine', fe);
    url.searchParams.set('sort', so);
    url.searchParams.delete('filter_no'); // 새로운 검색 시 개별 필터 해제
    window.location.href = url.href;
}

function closeFilterDropdowns(except = null) {
    document.querySelectorAll('.filter-select-control.is-open').forEach(control => {
        if (control === except) return;
        closeFilterDropdown(control);
    });
}

function closeFilterDropdown(control) {
    const trigger = control.querySelector('.filter-select-trigger');
    const menu = control.querySelector('.filter-select-menu');
    control.classList.remove('is-open');
    if (trigger) trigger.setAttribute('aria-expanded', 'false');
    if (menu) menu.classList.add('hidden');
}

function initializeFilterDropdowns() {
    document.querySelectorAll('.filter-control select').forEach(select => {
        const control = select.closest('.filter-control');
        if (!control || control.dataset.dropdownReady === 'true') return;

        control.dataset.dropdownReady = 'true';
        control.classList.add('filter-select-control');
        if (select.id === 'sortOrder') control.classList.add('filter-select-sort');
        select.classList.add('filter-native-select');
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'filter-select-trigger';
        trigger.id = `${select.id}Trigger`;
        trigger.setAttribute('role', 'combobox');
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');

        const label = document.createElement('span');
        label.className = 'filter-select-label';
        const chevron = document.createElement('i');
        chevron.className = 'fas fa-chevron-down filter-select-chevron';
        chevron.setAttribute('aria-hidden', 'true');
        trigger.append(label, chevron);

        const menu = document.createElement('div');
        menu.className = 'filter-select-menu hidden';
        menu.id = `${select.id}Options`;
        menu.setAttribute('role', 'listbox');
        menu.setAttribute('aria-labelledby', trigger.id);
        trigger.setAttribute('aria-controls', menu.id);
        control.append(trigger, menu);

        const options = [...select.options];
        const optionButtons = [];
        options.forEach(option => {
            if (option.disabled) {
                if (menu.childElementCount > 0) {
                    const separator = document.createElement('div');
                    separator.className = 'filter-select-separator';
                    separator.setAttribute('role', 'separator');
                    menu.appendChild(separator);
                }
                return;
            }

            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'filter-select-option';
            item.setAttribute('role', 'option');
            item.dataset.value = option.value;
            item.textContent = option.textContent.trim();
            item.addEventListener('click', () => {
                select.value = option.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                updateSelection();
                closeFilterDropdowns();
                trigger.focus();
            });
            item.addEventListener('keydown', event => {
                const currentIndex = optionButtons.indexOf(item);
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    const delta = event.key === 'ArrowDown' ? 1 : -1;
                    const nextOption = optionButtons[(currentIndex + delta + optionButtons.length) % optionButtons.length];
                    if (nextOption) nextOption.focus();
                } else if (event.key === 'Home' || event.key === 'End') {
                    event.preventDefault();
                    const edgeOption = optionButtons[event.key === 'Home' ? 0 : optionButtons.length - 1];
                    if (edgeOption) edgeOption.focus();
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    closeFilterDropdowns();
                    trigger.focus();
                }
            });
            optionButtons.push(item);
            menu.appendChild(item);
        });

        function updateSelection() {
            const selectedOption = select.options[select.selectedIndex];
            label.textContent = selectedOption ? selectedOption.textContent.trim() : '';
            optionButtons.forEach(item => {
                const selected = item.dataset.value === select.value;
                item.setAttribute('aria-selected', String(selected));
                item.classList.toggle('is-selected', selected);
            });
        }

        function openMenu(focusSelected = false) {
            closeFilterDropdowns(control);
            control.classList.add('is-open');
            menu.classList.remove('hidden');
            trigger.setAttribute('aria-expanded', 'true');
            if (focusSelected) {
                const selectedOption = optionButtons.find(item => item.dataset.value === select.value);
                if (selectedOption) selectedOption.focus();
            }
        }

        trigger.addEventListener('click', () => {
            if (control.classList.contains('is-open')) {
                closeFilterDropdowns();
            } else {
                openMenu();
            }
        });
        trigger.addEventListener('keydown', event => {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp' || event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openMenu(true);
            } else if (event.key === 'Escape') {
                closeFilterDropdowns();
            }
        });
        control.addEventListener('focusout', () => {
            setTimeout(() => {
                if (!control.contains(document.activeElement)) closeFilterDropdown(control);
            }, 0);
        });
        select.addEventListener('change', updateSelection);
        updateSelection();
    });
}

document.addEventListener('click', event => {
    if (!event.target.closest('.filter-select-control')) closeFilterDropdowns();
    if (!event.target.closest('.monitor-actions')) {
        document.querySelectorAll('.monitor-actions[open]').forEach(menu => {
            menu.open = false;
        });
    }
});

function filterByGroup(groupName) {
    const groupSelect = document.getElementById('searchGroup');
    if (groupSelect) groupSelect.value = groupName;
    triggerSearch();
}

function applyType(type) {
    const url = new URL(window.location.href);
    url.searchParams.set('type', type);
    url.searchParams.delete('filter_no'); // 타입 변경 시 개별 필터 해제
    window.location.href = url.href;
}

// 전체 선택 체크박스
const selectAllBtn = document.getElementById('selectAll');
if (selectAllBtn) {
    selectAllBtn.addEventListener('change', function(e) {
        document.querySelectorAll('.item-checkbox').forEach(cb => {
            cb.checked = e.target.checked;
        });
        updateBatchActionBar();
    });
}

// 일괄 상태 변경 드롭다운 메뉴 토글
function toggleBulkDropdown(e) {
    e.stopPropagation();
    const dropdown = document.getElementById('bulkStatusDropdown');
    if (dropdown) {
        dropdown.classList.toggle('hidden');
    }
}

// 화면 다른 곳 클릭 시 드롭다운 닫기
window.onclick = function(e) {
    const dropdown = document.getElementById('bulkStatusDropdown');
    if (dropdown && !dropdown.contains(e.target) && !e.target.closest('.relative')) {
        dropdown.classList.add('hidden');
    }
}

function openSideSheet(id) {
    const overlay = document.getElementById(id);
    if (!overlay) return;
    overlay.classList.remove('hidden');
    overlay.classList.add('flex');
    document.body.classList.add('has-open-sheet');
    requestAnimationFrame(() => overlay.classList.add('is-open'));
}

function closeSideSheet(id) {
    const overlay = document.getElementById(id);
    if (!overlay) return;
    overlay.classList.remove('is-open');
    setTimeout(() => {
        if (!overlay.classList.contains('is-open')) {
            overlay.classList.remove('flex');
            overlay.classList.add('hidden');
            if (!document.querySelector('.side-sheet-overlay.is-open')) {
                document.body.classList.remove('has-open-sheet');
            }
        }
    }, 220);
}

document.querySelectorAll('.side-sheet-overlay').forEach(overlay => {
    overlay.addEventListener('click', event => {
        if (event.target === overlay) closeSideSheet(overlay.id);
    });
});

const confirmDialogElement = document.getElementById('confirmDialog');
if (confirmDialogElement) {
    confirmDialogElement.addEventListener('click', event => {
        if (event.target === confirmDialogElement) closeConfirmDialog();
    });
}

function openConfirmDialog({ title, message, items = [], actionLabel, onConfirm, tone = 'danger' }) {
    const dialog = document.getElementById('confirmDialog');
    const isKo = document.documentElement.lang === 'ko';
    const titleEl = document.getElementById('confirmDialogTitle');
    const messageEl = document.getElementById('confirmDialogMessage');
    const targetsEl = document.getElementById('confirmDialogTargets');
    const expandButton = document.getElementById('confirmDialogExpand');
    const actionButton = document.getElementById('confirmDialogAction');
    if (!dialog || !titleEl || !messageEl || !targetsEl || !expandButton || !actionButton) return;

    titleEl.textContent = title;
    messageEl.textContent = message;
    actionButton.textContent = actionLabel || (isKo ? '확인' : 'Confirm');
    const isDanger = tone === 'danger';
    const iconContainer = dialog.querySelector('.confirm-dialog-icon');
    if (iconContainer) {
        iconContainer.className = `confirm-dialog-icon mb-4 flex h-10 w-10 items-center justify-center rounded-full ${isDanger ? 'bg-red-50 text-red-600 dark:bg-red-950/30 dark:text-red-400' : 'bg-sky-50 text-[#18aee7] dark:bg-sky-950/30'}`;
        iconContainer.innerHTML = `<i class="fas ${isDanger ? 'fa-exclamation' : 'fa-circle-info'}"></i>`;
    }
    actionButton.className = `rounded-xl px-4 py-2.5 text-sm font-semibold text-white ${isDanger ? 'bg-red-600 hover:bg-red-700' : 'bg-[#18aee7] hover:bg-[#159bd0]'}`;
    targetsEl.replaceChildren();
    const initialVisibleItems = 20;
    const renderItems = visibleCount => {
        targetsEl.replaceChildren();
        items.slice(0, visibleCount).forEach(item => {
        const li = document.createElement('li');
        li.textContent = item;
        targetsEl.appendChild(li);
        });
    };
    renderItems(Math.min(items.length, initialVisibleItems));
    targetsEl.classList.toggle('hidden', items.length === 0);
    expandButton.classList.toggle('hidden', items.length <= initialVisibleItems);
    expandButton.dataset.expanded = 'false';
    expandButton.textContent = isKo ? `전체 ${items.length}개 항목 보기` : `Show all ${items.length} items`;
    window.pendingConfirmItems = items;
    dialog.dataset.pendingAction = 'true';
    window.pendingConfirmAction = onConfirm;
    dialog.classList.remove('hidden');
    dialog.classList.add('flex');
    actionButton.focus();
}

function toggleConfirmTargets() {
    const button = document.getElementById('confirmDialogExpand');
    const targets = document.getElementById('confirmDialogTargets');
    if (!button || !targets) return;
    const isKo = document.documentElement.lang === 'ko';
    const expanded = button.dataset.expanded !== 'true';
    if (expanded) {
        (window.pendingConfirmItems || []).forEach((item, index) => {
            if (index < 20) return;
            const li = document.createElement('li');
            li.textContent = item;
            targets.appendChild(li);
        });
        button.textContent = isKo ? '목록 접기' : 'Show fewer';
    } else {
        while (targets.children.length > 20) {
            targets.lastElementChild.remove();
        }
        targets.scrollTop = 0;
        button.textContent = isKo
            ? `전체 ${(window.pendingConfirmItems || []).length}개 항목 보기`
            : `Show all ${(window.pendingConfirmItems || []).length} items`;
    }
    button.dataset.expanded = String(expanded);
}

function closeConfirmDialog() {
    const dialog = document.getElementById('confirmDialog');
    if (!dialog) return;
    dialog.classList.add('hidden');
    dialog.classList.remove('flex');
    delete dialog.dataset.pendingAction;
    window.pendingConfirmAction = null;
    window.pendingConfirmItems = [];
}

function runConfirmedAction() {
    const dialog = document.getElementById('confirmDialog');
    if (!dialog || !dialog.dataset.pendingAction) return;
    const action = window.pendingConfirmAction;
    closeConfirmDialog();
    if (typeof action === 'function') action();
}

document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
        const dialog = document.getElementById('confirmDialog');
        if (dialog && !dialog.classList.contains('hidden')) {
            closeConfirmDialog();
            return;
        }
        document.querySelectorAll('.side-sheet-overlay.is-open').forEach(overlay => {
            closeSideSheet(overlay.id);
        });
    }
});

// 모달창 열기 및 데이터 세팅
function openModal(type, data = null) {
    const modal = document.getElementById('addModal');
    const form = document.getElementById('modalForm');

    // [추가된 부분] 현재 검색/필터 쿼리 스트링 유지
    const currentQueryField = document.getElementById('modalCurrentQuery');
    if (currentQueryField) {
        currentQueryField.value = window.location.search.replace('?', '');
    }

    openSideSheet('addModal');
    
    const modalTitle = document.getElementById('modalTitleText');
    if (modalTitle) {
        modalTitle.textContent = type === 'add' ? (typeof langPack !== 'undefined' ? langPack.modalAddTitle : 'Add Monitor') : (typeof langPack !== 'undefined' ? langPack.modalEditTitle : 'Edit Monitor');
    }
    if (document.getElementById('modalAction')) document.getElementById('modalAction').value = type;

    if (type === 'edit' && data) {
        if (document.getElementById('modalNo')) document.getElementById('modalNo').value = data.no;
        if (document.getElementById('modalCompany')) document.getElementById('modalCompany').value = data.company;
        if (document.getElementById('modalUrl')) document.getElementById('modalUrl').value = data.url;
        if (document.getElementById('modalContents')) document.getElementById('modalContents').value = data.contents;
        if (document.getElementById('modalComment')) document.getElementById('modalComment').value = data.comment || '';
        if (document.getElementById('modalEmailMode')) document.getElementById('modalEmailMode').value = data.email_mode || 'none';
        if (document.getElementById('modalSenderEmail')) document.getElementById('modalSenderEmail').value = data.sender_email || '';
        if (document.getElementById('modalReceiverEmail')) document.getElementById('modalReceiverEmail').value = data.receiver_email || '';
        
        // 개별 SMTP 필드 로딩
        if (document.getElementById('modalSmtpHost')) document.getElementById('modalSmtpHost').value = data.smtp_host || '';
        if (document.getElementById('modalSmtpPort')) document.getElementById('modalSmtpPort').value = data.smtp_port || '25';
        if (document.getElementById('modalSmtpSecure')) document.getElementById('modalSmtpSecure').value = data.smtp_secure || 'none';
        if (document.getElementById('modalSmtpUser')) document.getElementById('modalSmtpUser').value = data.smtp_user || '';
        if (document.getElementById('modalSmtpPass')) document.getElementById('modalSmtpPass').value = data.smtp_pass || '';
        
        // 알람 상태 셋팅
        if (document.getElementById('modalCheck')) document.getElementById('modalCheck').value = data.check;
        if (document.getElementById('modalSsl')) document.getElementById('modalSsl').value = data.ssl_mon;
        if (document.getElementById('modalDom')) document.getElementById('modalDom').value = data.dom_mon;

        // 엔진 및 횟수, 타임아웃
        if (document.getElementById('modalRetry')) document.getElementById('modalRetry').value = data.retry_count || '1';
        if (document.getElementById('modalTimeout')) document.getElementById('modalTimeout').value = data.timeout_sec || '2';
        
        const engine = data.engine_type || 'lynx';
        const engineRadio = document.querySelector('input[name="engine_type"][value="' + engine + '"]');
        if (engineRadio) engineRadio.checked = true;

        if (document.getElementById('modalGroupSelect')) {
            const select = document.getElementById('modalGroupSelect');
            const groupName = data.group_name || '기본 그룹';
            let optionExists = false;
            for (let i = 0; i < select.options.length; i++) {
                if (select.options[i].value === groupName) optionExists = true;
            }
            if (!optionExists) {
                const newOption = new Option(groupName, groupName);
                select.insertBefore(newOption, select.options[select.options.length - 1]);
            }
            select.value = groupName;
            toggleGroupNewInput();
        }
        
        toggleModalSmtpFields();

    } else {
        if (form) form.reset();
        if (document.getElementById('modalAction')) document.getElementById('modalAction').value = 'add';
        
        // 기본값 적용
        const defaults = typeof defaultSettings !== 'undefined' ? defaultSettings : {
            emailMode: 'none', senderEmail: '', receiverEmail: '', check: 'alarm',
            sslMon: '1', domMon: '1', engineType: 'lynx', retryCount: '1', timeoutSec: '2'
        };

        if (document.getElementById('modalComment')) document.getElementById('modalComment').value = '';
        if (document.getElementById('modalEmailMode')) document.getElementById('modalEmailMode').value = defaults.emailMode;
        if (document.getElementById('modalSenderEmail')) document.getElementById('modalSenderEmail').value = defaults.senderEmail;
        if (document.getElementById('modalReceiverEmail')) document.getElementById('modalReceiverEmail').value = defaults.receiverEmail;
        if (document.getElementById('modalRetry')) document.getElementById('modalRetry').value = defaults.retryCount;
        if (document.getElementById('modalTimeout')) document.getElementById('modalTimeout').value = defaults.timeoutSec;
        
        // 개별 SMTP 필드 초기화
        if (document.getElementById('modalSmtpHost')) document.getElementById('modalSmtpHost').value = '';
        if (document.getElementById('modalSmtpPort')) document.getElementById('modalSmtpPort').value = '25';
        if (document.getElementById('modalSmtpSecure')) document.getElementById('modalSmtpSecure').value = 'none';
        if (document.getElementById('modalSmtpUser')) document.getElementById('modalSmtpUser').value = '';
        if (document.getElementById('modalSmtpPass')) document.getElementById('modalSmtpPass').value = '';
        
        if (document.getElementById('modalCheck')) document.getElementById('modalCheck').value = defaults.check;
        if (document.getElementById('modalSsl')) document.getElementById('modalSsl').value = defaults.sslMon;
        if (document.getElementById('modalDom')) document.getElementById('modalDom').value = defaults.domMon;
        
        const engine = defaults.engineType || 'lynx';
        const engineRadio = document.querySelector('input[name="engine_type"][value="' + engine + '"]');
        if (engineRadio) engineRadio.checked = true;

        if (document.getElementById('modalGroupSelect')) {
            document.getElementById('modalGroupSelect').selectedIndex = 0;
            toggleGroupNewInput();
        }
        
        toggleModalSmtpFields();
    }
}

function closeModal() {
    closeSideSheet('addModal');
}

function openBulkModal() {
    const checked = document.querySelectorAll('.item-checkbox:checked');
    if (checked.length === 0) {
        showToast(typeof langPack !== 'undefined' && langPack.selStatus ? langPack.selStatus : '변경할 항목을 선택해주세요.', 'warning');
        return;
    }
    
    // 카드 뷰와 리스트 뷰 중복 체크박스 ID 고유값만 추출
    const uniqueIds = Array.from(new Set(Array.from(checked).map(cb => cb.value)));
    const ids = uniqueIds.join(',');
    
    const idsField = document.getElementById('bulkModalIds');
    if (idsField) idsField.value = ids;
    
    const queryField = document.getElementById('bulkModalCurrentQuery');
    if (queryField) queryField.value = window.location.search.replace('?', '');
    
    const countSpan = document.getElementById('bulkModalCount');
    if (countSpan) {
        const isKo = document.documentElement.lang === 'ko';
        countSpan.innerText = isKo ? `${uniqueIds.length}개 선택됨` : `${uniqueIds.length} selected`;
    }
    
    resetBulkModalForm();
    
    openSideSheet('bulkModal');
}

function resetBulkModalForm() {
    const form = document.getElementById('bulkModalForm');
    if (!form) return;
    form.reset();
    
    const fieldsToDisable = [
        { chk: 'chkGroup', inputs: ['bulkGroupSelect', 'bulkGroupNew'] },
        { chk: 'chkCheck', inputs: ['bulkCheck'] },
        { chk: 'chkEngine', inputs: ['bulkEngineLynx', 'bulkEngineCurl'] },
        { chk: 'chkLimits', inputs: ['bulkRetry', 'bulkTimeout'] },
        { chk: 'chkExpiry', inputs: ['bulkSsl', 'bulkDom'] },
        { chk: 'chkEmail', inputs: ['bulkEmailMode', 'bulkSenderEmail', 'bulkReceiverEmail'] },
        { chk: 'chkSmtp', inputs: ['bulkSmtpHost', 'bulkSmtpPort', 'bulkSmtpSecure', 'bulkSmtpUser', 'bulkSmtpPass'] }
    ];
    
    fieldsToDisable.forEach(item => {
        const chk = document.getElementById(item.chk);
        if (chk) {
            chk.checked = false;
            chk.dispatchEvent(new Event('change'));
        }
        item.inputs.forEach(inputId => {
            const input = document.getElementById(inputId);
            if (input) {
                input.disabled = true;
                input.required = false;
            }
        });
    });
    
    const smtpContainer = document.getElementById('bulkSmtpContainer');
    if (smtpContainer) smtpContainer.classList.add('hidden');
}

function closeBulkModal() {
    closeSideSheet('bulkModal');
}

function openHistoryModal() {
    openSideSheet('historyModal');
}

function closeHistoryModal() {
    closeSideSheet('historyModal');
}

function toggleBulkField(chkId, inputIds) {
    const chk = document.getElementById(chkId);
    if (!chk) return;
    
    inputIds.forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.disabled = !chk.checked;
            
            // 텍스트, 이메일, 숫자, 패스워드 입력창 성격의 필드만 required 지정
            const isTextInput = (input.tagName === 'INPUT' && ['text', 'number', 'email', 'password'].includes(input.type));
            if (isTextInput) {
                if (chk.checked) {
                    const isEmailAddr = ['bulkSenderEmail', 'bulkReceiverEmail'].includes(id);
                    if (isEmailAddr) {
                        const emailMode = document.getElementById('bulkEmailMode');
                        input.required = (emailMode && emailMode.value !== 'none');
                    } else {
                        input.required = true;
                    }
                } else {
                    input.required = false;
                }
            }
        }
    });
}

function toggleBulkGroupNewInput() {
    const sel = document.getElementById('bulkGroupSelect');
    const input = document.getElementById('bulkGroupNew');
    if (sel && input) {
        if (sel.value === '_NEW_' && !sel.disabled) {
            input.classList.remove('hidden');
            input.required = true;
            input.focus();
        } else {
            input.classList.add('hidden');
            input.required = false;
        }
    }
}

function toggleBulkSmtpVisibility() {
    const chkEmail = document.getElementById('chkEmail');
    const emailMode = document.getElementById('bulkEmailMode');
    const smtpContainer = document.getElementById('bulkSmtpContainer');
    const chkSmtp = document.getElementById('chkSmtp');
    
    // 수/발신 이메일 required 연동
    const sender = document.getElementById('bulkSenderEmail');
    const receiver = document.getElementById('bulkReceiverEmail');
    const isEmailActive = chkEmail && chkEmail.checked && emailMode && emailMode.value !== 'none';
    
    if (sender) sender.required = isEmailActive;
    if (receiver) receiver.required = isEmailActive;
    
    if (chkEmail && chkEmail.checked && emailMode && emailMode.value === 'smtp') {
        if (smtpContainer) smtpContainer.classList.remove('hidden');
    } else {
        if (smtpContainer) smtpContainer.classList.add('hidden');
        if (chkSmtp) {
            chkSmtp.checked = false;
            chkSmtp.dispatchEvent(new Event('change'));
        }
    }
}

function toggleGroupNewInput() {
    const sel = document.getElementById('modalGroupSelect');
    const input = document.getElementById('modalGroupNew');
    if (sel && input) {
        if (sel.value === '_NEW_') {
            input.classList.remove('hidden');
            input.required = true;
            input.focus();
        } else {
            input.classList.add('hidden');
            input.required = false;
        }
    }
}

function toggleModalSmtpFields() {
    const emailMode = document.getElementById('modalEmailMode');
    const smtpFields = document.getElementById('modalSmtpFields');
    
    // 수/발신 이메일 required 연동
    const sender = document.getElementById('modalSenderEmail');
    const receiver = document.getElementById('modalReceiverEmail');
    const isEmailActive = emailMode && emailMode.value !== 'none';
    if (sender) sender.required = isEmailActive;
    if (receiver) receiver.required = isEmailActive;
    
    // SMTP 필수 입력란 required 연동
    const smtpHost = document.getElementById('modalSmtpHost');
    const smtpPort = document.getElementById('modalSmtpPort');
    const smtpUser = document.getElementById('modalSmtpUser');
    const isSmtp = emailMode && emailMode.value === 'smtp';
    
    if (smtpHost) smtpHost.required = isSmtp;
    if (smtpPort) smtpPort.required = isSmtp;
    if (smtpUser) smtpUser.required = isSmtp;
    
    if (emailMode && smtpFields) {
        if (isSmtp) {
            smtpFields.classList.remove('hidden');
        } else {
            smtpFields.classList.add('hidden');
        }
    }
}

function setCookie(name, value, days) {
    let expires = "";
    if (days) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + (value || "")  + expires + "; path=/";
}

function toggleViewMode(mode, expandNo = null) {
    setCookie('webmon_view', mode, 365);
    const url = new URL(window.location.href);
    if (expandNo) {
        url.searchParams.set('expand', expandNo);
        url.searchParams.set('filter_no', expandNo); // 리스트 뷰 진입 시 해당 번호만 보여주도록 필터링
    } else {
        url.searchParams.delete('expand');
        url.searchParams.delete('filter_no');
    }
    window.location.href = url.href;
}

function toggleTheme(theme) {
    setCookie('webmon_theme', theme, 365);
    location.reload();
}

function toggleLang(lang) {
    setCookie('webmon_lang', lang, 365);
    location.reload();
}

function confirmDelete(company, no) {
    const isKo = document.documentElement.lang === 'ko';
    const msg = typeof langPack !== 'undefined' && langPack.msg_confirm_del
        ? langPack.msg_confirm_del
        : (isKo ? '이 모니터링 항목을 삭제할까요?' : 'Delete this monitoring item?');
    openConfirmDialog({
        title: isKo ? '모니터링 항목 삭제' : 'Delete monitor',
        message: msg,
        items: [company],
        actionLabel: isKo ? '삭제' : 'Delete',
        onConfirm: () => {
            const currentQuery = window.location.search.replace('?', '');
            location.href = `website_status.php?action=delete&no=${no}&current_query=${encodeURIComponent(currentQuery)}`;
        }
    });
}

function confirmBulkDeleteSubmit() {
    const checked = document.querySelectorAll('.item-checkbox:checked');
    if (checked.length === 0) {
        showToast(typeof langPack !== 'undefined' && langPack.selDel ? langPack.selDel : '삭제할 항목을 선택해주세요.', 'warning');
        return;
    }
    const unique = Array.from(new Map(Array.from(checked, cb => [cb.value, cb])).values());
    const isKo = document.documentElement.lang === 'ko';
    openConfirmDialog({
        title: isKo ? '선택 항목 삭제' : 'Delete selected items',
        message: isKo
            ? `${unique.length}개 모니터링 항목을 삭제할까요? 작업 이력에서 복원할 수 있습니다.`
            : `Delete ${unique.length} monitoring items? You can restore them from job history.`,
        items: unique.map(cb => cb.getAttribute('data-company') || cb.value),
        actionLabel: isKo ? '모두 삭제' : 'Delete all',
        onConfirm: () => {
        const ids = unique.map(cb => cb.value).join(',');
        document.getElementById('bulkAction').value = 'bulk_delete';
        document.getElementById('bulkIds').value = ids;
        
        // [추가된 부분] 일괄 삭제 시 현재 쿼리 유지
        const bulkQueryField = document.getElementById('bulkCurrentQuery');
        if (bulkQueryField) bulkQueryField.value = window.location.search.replace('?', '');
        
        document.getElementById('bulkForm').submit();
        }
    });
}

function confirmBulkStatusUpdate(status) {
    const checked = document.querySelectorAll('.item-checkbox:checked');
    if (checked.length === 0) {
        showToast(typeof langPack !== 'undefined' && langPack.selStatus ? langPack.selStatus : '상태를 변경할 항목을 선택해주세요.', 'warning');
        return;
    }
    let statusName = status;
    if(status === 'alarm') statusName = typeof langPack !== 'undefined' && langPack.statusAlarm ? langPack.statusAlarm : '활성화';
    if(status === 'no alarm') statusName = typeof langPack !== 'undefined' && langPack.statusNoAlarm ? langPack.statusNoAlarm : '무음';
    if(status === 'disable') statusName = typeof langPack !== 'undefined' && langPack.statusDisable ? langPack.statusDisable : '비활성화';
    if(status === 'alarm_unmute') statusName = typeof langPack !== 'undefined' && langPack.statusUnmute ? langPack.statusUnmute : '일시 무음 해제';
    if(status === 'alarm_mute_default') statusName = typeof langPack !== 'undefined' && langPack.statusMuteDefault ? langPack.statusMuteDefault : '일시 무음 설정';

    const unique = Array.from(new Map(Array.from(checked, cb => [cb.value, cb])).values());
    const isKo = document.documentElement.lang === 'ko';
    openConfirmDialog({
        title: isKo ? '알람 상태 변경' : 'Change alert status',
        message: isKo
            ? `${unique.length}개 항목의 알람 상태를 '${statusName}'(으)로 변경할까요?`
            : `Change the alert status of ${unique.length} items to '${statusName}'?`,
        items: unique.map(cb => cb.getAttribute('data-company') || cb.value),
        actionLabel: isKo ? '변경' : 'Change',
        tone: 'neutral',
        onConfirm: () => {
        const ids = unique.map(cb => cb.value).join(',');
        document.getElementById('bulkAction').value = 'bulk_status';
        document.getElementById('bulkStatusValue').value = status;
        document.getElementById('bulkIds').value = ids;
        
        // [추가된 부분] 일괄 상태 변경 시 현재 쿼리 유지
        const bulkQueryField = document.getElementById('bulkCurrentQuery');
        if (bulkQueryField) bulkQueryField.value = window.location.search.replace('?', '');
        
        document.getElementById('bulkForm').submit();
        }
    });
}

function toggleSortStatus() {
    const url = new URL(window.location.href);
    let currentSort = url.searchParams.get('sort');
    url.searchParams.set('sort', currentSort === 'status_desc' ? 'status_asc' : 'status_desc');
    window.location.href = url.href;
}

function toggleSortResult() {
    const url = new URL(window.location.href);
    let currentSort = url.searchParams.get('sort');
    url.searchParams.set('sort', currentSort === 'result_succ' ? 'result_fail' : 'result_succ');
    window.location.href = url.href;
}

function toggleSortCompany() {
    const url = new URL(window.location.href);
    let currentSort = url.searchParams.get('sort') || 'latest';
    url.searchParams.set('sort', currentSort === 'comp_asc' ? 'comp_desc' : 'comp_asc');
    window.location.href = url.href;
}

let autoRefreshTimer = null;

function toggleAutoRefresh(enabled) {
    if (enabled) {
        sessionStorage.setItem('webmon_auto_refresh', 'true');
        startAutoRefresh();
    } else {
        sessionStorage.removeItem('webmon_auto_refresh');
        stopAutoRefresh();
    }
}

function startAutoRefresh() {
    stopAutoRefresh();
    autoRefreshTimer = setInterval(() => {
        const searchInput = document.getElementById('searchCompany');
        if (searchInput && document.activeElement === searchInput) {
            return;
        }
        
        // 사용자가 로그 목록을 호버하고 있거나 활성화된 툴팁이 존재하는 경우 새로고침 스킵
        const logWrapper = document.querySelector('.view-list.flex.flex-col.gap-3');
        if (logWrapper) {
            const isHovered = logWrapper.matches(':hover');
            const hasVisibleTooltip = Array.from(document.querySelectorAll('.cmd-tooltip')).some(el => {
                return window.getComputedStyle(el).display !== 'none';
            });
            if (isHovered || hasVisibleTooltip) {
                return;
            }
        }
        
        refreshLogs();
    }, 10000); // 10 seconds interval
}

async function refreshLogs() {
    const refreshBtnIcon = document.querySelector('button[onclick*="refreshLogs"] i');
    if (refreshBtnIcon) {
        refreshBtnIcon.classList.add('fa-spin');
    }
    
    try {
        const url = 'get_logs.php' + window.location.search;
        const response = await fetch(url);
        if (!response.ok) throw new Error('Network response was not ok');
        const html = await response.text();
        
        const logWrapper = document.querySelector('.view-list.flex.flex-col.gap-3');
        if (logWrapper) {
            logWrapper.innerHTML = html;
        }
    } catch (err) {
        console.error('Failed to refresh logs:', err);
    } finally {
        if (refreshBtnIcon) {
            setTimeout(() => {
                refreshBtnIcon.classList.remove('fa-spin');
            }, 500);
        }
    }
}

function stopAutoRefresh() {
    if (autoRefreshTimer) {
        clearInterval(autoRefreshTimer);
        autoRefreshTimer = null;
    }
}

function updateBatchActionBar() {
    const checked = document.querySelectorAll('.item-checkbox:checked');
    const bar = document.getElementById('batchActionBar');
    const countSpan = document.getElementById('batchCount');
    if (!bar || !countSpan) return;

    const count = checked.length;
    countSpan.innerText = count;

    if (count > 0) {
        bar.classList.remove('hidden');
        requestAnimationFrame(() => {
            bar.classList.remove('opacity-0', '-translate-x-2', 'pointer-events-none');
            bar.classList.add('opacity-100', 'translate-x-0', 'pointer-events-auto');
        });
    } else {
        bar.classList.remove('opacity-100', 'translate-x-0', 'pointer-events-auto');
        bar.classList.add('opacity-0', '-translate-x-2', 'pointer-events-none');
        setTimeout(() => {
            if (document.querySelectorAll('.item-checkbox:checked').length === 0) {
                bar.classList.add('hidden');
            }
        }, 300);
    }
}

window.addEventListener('DOMContentLoaded', () => {
    initializeFilterDropdowns();
    const autoRefreshToggle = document.getElementById('autoRefreshToggle');
    if (autoRefreshToggle) {
        const saved = sessionStorage.getItem('webmon_auto_refresh') === 'true';
        autoRefreshToggle.checked = saved;
        if (saved) {
            startAutoRefresh();
        }
    }
    
    // 개별 체크박스 리스너 등록
    document.querySelectorAll('.item-checkbox').forEach(cb => {
        cb.addEventListener('change', updateBatchActionBar);
    });
    updateBatchActionBar();
    
    // URL에 open_history=1 파라미터가 감지되면 작업 이력 모달 자동 팝업
    const params = new URLSearchParams(window.location.search);
    if (params.has('open_history')) {
        setTimeout(() => {
            openHistoryModal();
            // URL을 지저분하지 않게 정리
            const cleanUrl = window.location.pathname + window.location.search.replace(/[?&]open_history=1/, '').replace(/^&/, '?').replace(/\?$/, '');
            window.history.replaceState({}, document.title, cleanUrl);
        }, 300);
    }
});

// 스크롤 중 툴팁이 둥둥 떠다니는 현상을 차단하기 위해 스크롤 감지 처리 추가
let scrollTimeout = null;
window.addEventListener('scroll', () => {
    document.body.classList.add('is-scrolling');
    clearTimeout(scrollTimeout);
    scrollTimeout = setTimeout(() => {
        document.body.classList.remove('is-scrolling');
    }, 150);
}, { passive: true });

function copyCmd(button) {
    const cmd = button.getAttribute('data-cmd');
    if (!cmd) return;
    
    const onSuccess = () => {
        const icon = button.querySelector('i');
        if (icon) {
            const originalClass = icon.className;
            icon.className = 'fas fa-check text-green-500 text-[10px]';
            setTimeout(() => {
                icon.className = originalClass;
            }, 1000);
        }
        
        const isKo = document.documentElement.lang === 'ko';
        const msg = isKo ? '클립보드에 복사되었습니다.' : 'Copied to clipboard!';
        showToast(msg);
    };

    // HTTPS 보안 컨텍스트 환경 대응
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(cmd)
            .then(onSuccess)
            .catch(err => {
                console.warn('Modern clipboard API failed, trying fallback...', err);
                fallbackCopy(cmd, onSuccess);
            });
    } else {
        // 일반 HTTP 및 사설 IP 접속 환경을 위한 폴백 복사 대응
        fallbackCopy(cmd, onSuccess);
    }
}

function fallbackCopy(text, callback) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.top = '0';
    textArea.style.left = '0';
    textArea.style.width = '2em';
    textArea.style.height = '2em';
    textArea.style.padding = '0';
    textArea.style.border = 'none';
    textArea.style.outline = 'none';
    textArea.style.boxShadow = 'none';
    textArea.style.background = 'transparent';
    
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        const successful = document.execCommand('copy');
        if (successful) {
            callback();
        } else {
            console.error('Fallback copy failed');
        }
    } catch (err) {
        console.error('Fallback copy error:', err);
    }
    
    document.body.removeChild(textArea);
}

function showToast(message, type = 'success') {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        container.setAttribute('role', 'status');
        container.setAttribute('aria-live', 'polite');
        document.body.appendChild(container);
    }
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    const icon = document.createElement('i');
    icon.className = `fas ${type === 'error' ? 'fa-circle-exclamation' : type === 'warning' ? 'fa-triangle-exclamation' : 'fa-check-circle'}`;
    const text = document.createElement('span');
    text.textContent = message;
    toast.append(icon, text);
    container.appendChild(toast);
    
    // Trigger layout reflow for CSS transition
    toast.offsetHeight;
    
    toast.classList.add('show');
    
    setTimeout(() => {
        toast.classList.replace('show', 'hide');
        toast.addEventListener('transitionend', () => {
            toast.remove();
        });
    }, 3200);
}

/**
 * 개별 아이템 즉시 재진단 및 만료일 동기화
 */
async function diagnoseItem(no, button) {
    if (button.classList.contains('fa-spin')) return;
    
    button.classList.add('fa-spin');
    
    try {
        const response = await fetch(`website_status.php?action=diagnose&no=${no}`);
        if (!response.ok) throw new Error('Network response was not ok');
        const data = await response.json();
        
        if (data.success) {
            const isKo = document.documentElement.lang === 'ko';
            const msg = isKo ? '재진단 및 만료일 동기화 완료!' : 'Diagnosis & Sync Completed!';
            showToast(msg);
            
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showToast(data.message || '진단 중 에러가 발생했습니다.', 'error');
        }
    } catch (err) {
        console.error('Failed to diagnose item:', err);
        showToast('진단 요청 실패: ' + err.message, 'error');
    } finally {
        setTimeout(() => {
            button.classList.remove('fa-spin');
        }, 1000);
    }
}
