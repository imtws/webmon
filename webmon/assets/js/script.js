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
                if (select.disabled || option.disabled) return;
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
        syncFilterDropdown(select);
    });
}

function syncFilterDropdown(select) {
    const control = select.closest('.filter-select-control');
    const trigger = control ? control.querySelector('.filter-select-trigger') : null;
    if (!control || !trigger) return;

    trigger.disabled = select.disabled;
    trigger.setAttribute('aria-disabled', String(select.disabled));
    control.classList.toggle('is-disabled', select.disabled);
    const selectedOption = select.options[select.selectedIndex];
    const label = trigger.querySelector('.filter-select-label');
    if (label) label.textContent = selectedOption ? selectedOption.textContent.trim() : '';
    control.querySelectorAll('.filter-select-option').forEach(option => {
        const selected = option.dataset.value === select.value;
        option.setAttribute('aria-selected', String(selected));
        option.classList.toggle('is-selected', selected);
    });
    if (select.disabled) closeFilterDropdown(control);
}

document.addEventListener('click', event => {
    const loadMoreLogsButton = event.target.closest('.load-more-logs');
    if (loadMoreLogsButton) {
        loadOlderLogs(loadMoreLogsButton);
        return;
    }

    if (!event.target.closest('.filter-select-control')) closeFilterDropdowns();
    if (!event.target.closest('.monitor-actions')) {
        document.querySelectorAll('.monitor-actions[open]').forEach(menu => {
            menu.open = false;
        });
    }

    const item = event.target.closest('.monitor-item');
    if (!item || event.target.closest('button, a, input, select, textarea, label, summary, details, [role="button"], [onclick], .clickable')) return;

    const checkbox = item.querySelector('.item-checkbox');
    if (checkbox) {
        checkbox.checked = !checkbox.checked;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
    }
});

document.addEventListener('change', event => {
    if (event.target.matches('.item-checkbox')) updateBatchActionBar();
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
        if (document.getElementById('modalEngine')) document.getElementById('modalEngine').value = data.engine_type || 'lynx';
        if (document.getElementById('modalRetry')) document.getElementById('modalRetry').value = data.retry_count || '1';
        if (document.getElementById('modalTimeout')) document.getElementById('modalTimeout').value = data.timeout_sec || '2';

        if (document.getElementById('modalGroupSelect')) {
            const select = document.getElementById('modalGroupSelect');
            const groupName = data.group_name || (document.documentElement.lang === 'ko' ? '기본 그룹' : 'Default Group');
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
        if (document.getElementById('modalEngine')) document.getElementById('modalEngine').value = defaults.engineType;
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

    const muteUntil = document.getElementById('bulkMuteUntil');
    if (muteUntil) {
        const defaultMuteUntil = new Date(Date.now() + 2 * 60 * 60 * 1000);
        const localDateTime = new Date(defaultMuteUntil.getTime() - defaultMuteUntil.getTimezoneOffset() * 60000);
        muteUntil.value = localDateTime.toISOString().slice(0, 16);
    }
    
    const fieldsToDisable = [
        { chk: 'chkGroup', inputs: ['bulkGroupSelect', 'bulkGroupNew'] },
        { chk: 'chkCheck', inputs: ['bulkCheck'] },
        { chk: 'chkEngine', inputs: ['bulkEngineType'] },
        { chk: 'chkLimits', inputs: ['bulkRetry', 'bulkTimeout'] },
        { chk: 'chkExpiry', inputs: ['bulkSsl', 'bulkDom'] },
        { chk: 'chkEmail', inputs: ['bulkEmailMode', 'bulkSenderEmail', 'bulkReceiverEmail'] },
        { chk: 'chkSmtp', inputs: ['bulkSmtpHost', 'bulkSmtpPort', 'bulkSmtpSecure', 'bulkSmtpUser', 'bulkSmtpPass'] },
        { chk: 'chkSchedule', inputs: ['bulkScheduleAction', 'bulkMuteUntil'] }
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
    toggleBulkScheduleFields();
}

function closeBulkModal() {
    closeSideSheet('bulkModal');
}

function openCsvImportModal() {
    const form = document.getElementById('csvImportForm');
    const status = document.getElementById('csvPreviewStatus');
    const preview = document.getElementById('csvPreviewWrap');
    const submit = document.getElementById('csvImportSubmit');
    if (form) form.reset();
    if (status) {
        status.classList.add('hidden');
        status.textContent = '';
    }
    if (preview) preview.classList.add('hidden');
    if (submit) {
        submit.disabled = true;
        submit.textContent = document.documentElement.lang === 'ko' ? '항목 가져오기' : 'Import monitors';
    }
    openSideSheet('csvImportModal');
}

function closeCsvImportModal() {
    closeSideSheet('csvImportModal');
}

function parseCsvRows(text) {
    const rows = [];
    let row = [];
    let field = '';
    let inQuotes = false;
    const source = text.replace(/^\uFEFF/, '');

    for (let i = 0; i < source.length; i++) {
        const char = source[i];
        if (inQuotes) {
            if (char === '"' && source[i + 1] === '"') {
                field += '"';
                i++;
            } else if (char === '"') {
                inQuotes = false;
            } else {
                field += char;
            }
        } else if (char === '"' && field === '') {
            inQuotes = true;
        } else if (char === ',') {
            row.push(field);
            field = '';
        } else if (char === '\n' || char === '\r') {
            row.push(field);
            if (row.some(value => value.trim() !== '')) rows.push(row);
            row = [];
            field = '';
            if (char === '\r' && source[i + 1] === '\n') i++;
        } else if (char === '"') {
            throw new Error('CSV에 잘못된 따옴표가 있습니다.');
        } else {
            field += char;
        }
    }

    if (inQuotes) throw new Error('CSV 따옴표가 닫히지 않았습니다.');
    row.push(field);
    if (row.some(value => value.trim() !== '')) rows.push(row);
    return rows;
}

async function previewCsvImport(file) {
    const isKo = document.documentElement.lang === 'ko';
    const status = document.getElementById('csvPreviewStatus');
    const preview = document.getElementById('csvPreviewWrap');
    const count = document.getElementById('csvPreviewCount');
    const body = document.getElementById('csvPreviewBody');
    const submit = document.getElementById('csvImportSubmit');
    const showStatus = (message, isError = false) => {
        status.textContent = message;
        status.classList.remove('hidden');
        status.classList.toggle('text-red-600', isError);
        status.classList.toggle('dark:text-red-400', isError);
    };

    submit.disabled = true;
    preview.classList.add('hidden');
    body.replaceChildren();
    if (!file) return;
    if (!/\.csv$/i.test(file.name) || file.size > 5 * 1024 * 1024) {
        showStatus(isKo ? 'CSV 파일(최대 5MB)만 선택할 수 있습니다.' : 'Choose a CSV file no larger than 5 MB.', true);
        return;
    }

    try {
        const rows = parseCsvRows(await file.text());
        if (rows.length < 2) throw new Error(isKo ? '헤더와 데이터 행이 필요합니다.' : 'The CSV must contain a header and at least one data row.');
        if (rows.length - 1 > 500) throw new Error(isKo ? '한 번에 최대 500행까지 가져올 수 있습니다.' : 'Import up to 500 rows at a time.');

        const headers = rows[0].map(value => value.trim().toLowerCase());
        const allowedHeaders = [
            'company', 'url', 'contents', 'group_name', 'check', 'ssl_mon', 'dom_mon',
            'comment', 'engine_type', 'retry_count', 'timeout_sec', 'email_mode',
            'sender_email', 'receiver_email'
        ];
        if (new Set(headers).size !== headers.length) {
            throw new Error(isKo ? 'CSV 헤더에 중복된 열이 있습니다.' : 'The CSV header contains duplicate columns.');
        }
        const unsupported = headers.filter(header => !allowedHeaders.includes(header));
        if (unsupported.length) {
            throw new Error((isKo ? '지원하지 않는 CSV 열: ' : 'Unsupported CSV columns: ') + unsupported.join(', '));
        }
        const indexes = Object.fromEntries(headers.map((header, index) => [header, index]));
        const required = ['company', 'url', 'contents'];
        const missing = required.filter(header => !Object.prototype.hasOwnProperty.call(indexes, header));
        if (missing.length) {
            throw new Error((isKo ? '필수 열이 없습니다: ' : 'Missing required columns: ') + missing.join(', '));
        }

        const records = rows.slice(1);
        if (records.some(values => values.length !== headers.length)) {
            throw new Error(isKo ? 'CSV 데이터 열 개수가 헤더와 일치하지 않습니다.' : 'A data row has a different number of columns than the header.');
        }
        if (records.some(values => required.some(header => !(values[indexes[header]] || '').trim()))) {
            throw new Error(isKo ? '회사명, URL, 키워드는 모든 데이터 행에 입력해야 합니다.' : 'Company, URL, and keyword are required on every row.');
        }

        count.textContent = isKo
            ? `총 ${records.length}개 행 · 처음 ${Math.min(records.length, 20)}개 미리보기`
            : `${records.length} rows · previewing ${Math.min(records.length, 20)}`;
        records.slice(0, 20).forEach((values, index) => {
            const tr = document.createElement('tr');
            [String(index + 2), values[indexes.company] || '', values[indexes.url] || '', values[indexes.contents] || '', indexes.group_name === undefined ? '' : values[indexes.group_name] || ''].forEach(value => {
                const td = document.createElement('td');
                td.className = 'max-w-[280px] truncate p-3';
                td.textContent = value;
                tr.appendChild(td);
            });
            body.appendChild(tr);
        });
        preview.classList.remove('hidden');
        showStatus(isKo ? '미리보기가 준비되었습니다. 등록 전 전체 행을 서버에서 검증합니다.' : 'Preview ready. The server validates every row before importing.');
        submit.disabled = false;
    } catch (error) {
        showStatus(error.message || (isKo ? 'CSV 파일을 읽지 못했습니다.' : 'Could not read the CSV file.'), true);
    }
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
            if (input.tagName === 'SELECT') syncFilterDropdown(input);
            
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

function toggleBulkScheduleFields() {
    const scheduleCheckbox = document.getElementById('chkSchedule');
    const enabled = Boolean(scheduleCheckbox && scheduleCheckbox.checked);
    const action = document.getElementById('bulkScheduleAction');
    const muteUntil = document.getElementById('bulkMuteUntil');
    const muteUntilContainer = document.getElementById('bulkMuteUntilContainer');
    const needsDate = Boolean(enabled && action && action.value === '1');

    if (muteUntil) {
        muteUntil.disabled = !needsDate;
        muteUntil.required = needsDate;
    }
    if (muteUntilContainer) muteUntilContainer.classList.toggle('hidden', !needsDate);
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

function toggleCardDensity() {
    const cookies = document.cookie.split('; ').reduce((values, entry) => {
        const [name, ...parts] = entry.split('=');
        values[name] = parts.join('=');
        return values;
    }, {});
    const nextColumns = cookies.webmon_card_columns === '2' ? '3' : '2';
    setCookie('webmon_card_columns', nextColumns, 365);
    setCookie('webmon_view', 'card', 365);
    window.location.reload();
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
        sessionStorage.setItem('webmon_auto_refresh', 'false');
        stopAutoRefresh();
    }
}

function startAutoRefresh() {
    stopAutoRefresh();
    autoRefreshTimer = setInterval(() => {
        if (document.hidden) return;
        const searchInput = document.getElementById('searchCompany');
        if (searchInput && document.activeElement === searchInput) {
            return;
        }

        const logWrapper = document.querySelector('.view-list.flex.flex-col.gap-3');
        if (logWrapper) {
            const isHovered = logWrapper.matches(':hover');
            const hasVisibleTooltip = Array.from(document.querySelectorAll('.cmd-tooltip')).some(el => {
                return window.getComputedStyle(el).display !== 'none';
            });
            if (isHovered || hasVisibleTooltip) {
                return;
            }
            refreshLogs();
        } else {
            refreshDashboard();
        }
    }, document.querySelector('.view-list.flex.flex-col.gap-3') ? 10000 : 30000);
}

function dashboardRefreshIsSafe() {
    const mainForm = document.getElementById('mainForm');
    if (!mainForm || mainForm.querySelector('.item-checkbox:checked')) return false;
    if (mainForm.matches(':hover') || document.querySelector('.monitor-actions[open]')) return false;
    if ([...mainForm.querySelectorAll('[id^="accordion-"]')].some(panel => !panel.classList.contains('hidden'))) return false;
    if ([...document.querySelectorAll('[role="dialog"][aria-modal="true"]')].some(dialog => dialog.getClientRects().length > 0)) return false;

    const activeElement = document.activeElement;
    return !activeElement || activeElement === document.body ||
        !activeElement.matches('input:not([type="checkbox"]), textarea, select');
}

async function refreshDashboard() {
    const currentForm = document.getElementById('mainForm');
    if (!currentForm || !dashboardRefreshIsSafe()) return;

    try {
        const response = await fetch(window.location.href, { cache: 'no-store' });
        if (!response.ok) throw new Error(`Dashboard refresh failed with HTTP ${response.status}`);
        const html = await response.text();
        const refreshedDocument = new DOMParser().parseFromString(html, 'text/html');
        const refreshedForm = refreshedDocument.getElementById('mainForm');
        if (!refreshedForm || !dashboardRefreshIsSafe()) return;

        const currentItems = Array.from(currentForm.children);
        const refreshedItems = Array.from(refreshedForm.children);
        const currentItemsAreKeyed = currentItems.every(item => item.dataset.monitorId);
        const refreshedItemsAreKeyed = refreshedItems.every(item => item.dataset.monitorId);

        if (currentItemsAreKeyed && refreshedItemsAreKeyed) {
            const existingById = new Map(currentItems.map(item => [item.dataset.monitorId, item]));
            refreshedItems.forEach((freshItem, index) => {
                const existing = existingById.get(freshItem.dataset.monitorId);
                let item = existing;
                if (!existing) {
                    item = freshItem;
                } else {
                    existingById.delete(freshItem.dataset.monitorId);
                    if (existing.classList.contains('is-flipped')) {
                        item = existing;
                    } else if (existing.outerHTML !== freshItem.outerHTML) {
                        existing.replaceWith(freshItem);
                        item = freshItem;
                    }
                }
                const position = currentForm.children[index] || null;
                if (position !== item) currentForm.insertBefore(item, position);
            });
            existingById.forEach(item => item.remove());
        } else {
            currentForm.innerHTML = refreshedForm.innerHTML;
        }
        ['statTotal', 'statSuccess', 'statFail', 'statExpiring', 'lastRunValue', 'nextRunValue'].forEach(id => {
            const currentValue = document.getElementById(id);
            const refreshedValue = refreshedDocument.getElementById(id);
            if (currentValue && refreshedValue) currentValue.textContent = refreshedValue.textContent;
        });
        updateBatchActionBar();
        refreshFlippedCharts();
    } catch (error) {
        console.error('Failed to refresh dashboard:', error);
    }
}

async function refreshLogs(force = false) {
    const logWrapper = document.querySelector('.view-list.flex.flex-col.gap-3');
    if (!logWrapper || (logWrapper.dataset.expanded === 'true' && !force)) return;

    const refreshBtnIcon = document.querySelector('button[onclick*="refreshLogs"] i');
    if (refreshBtnIcon) {
        refreshBtnIcon.classList.add('fa-spin');
    }
    
    try {
        const url = 'get_logs.php' + window.location.search;
        const response = await fetch(url);
        if (!response.ok) throw new Error('Network response was not ok');
        const html = await response.text();
        
        if (logWrapper) {
            logWrapper.innerHTML = html;
            logWrapper.dataset.expanded = 'false';
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

async function loadOlderLogs(button) {
    const logWrapper = button.closest('.view-list');
    if (!logWrapper || button.disabled) return;

    button.disabled = true;
    button.textContent = document.documentElement.lang === 'ko' ? '불러오는 중…' : 'Loading…';
    try {
        const url = new URL('get_logs.php', window.location.href);
        url.search = window.location.search;
        url.searchParams.set('before_log_id', button.dataset.cursor);
        const response = await fetch(url);
        if (!response.ok) throw new Error('Network response was not ok');
        const html = await response.text();
        button.remove();
        logWrapper.insertAdjacentHTML('beforeend', html);
        logWrapper.dataset.expanded = 'true';
    } catch (error) {
        console.error('Failed to load older logs:', error);
        button.disabled = false;
        button.textContent = document.documentElement.lang === 'ko' ? '다시 시도' : 'Try again';
    }
}

function stopAutoRefresh() {
    if (autoRefreshTimer) {
        clearInterval(autoRefreshTimer);
        autoRefreshTimer = null;
    }
}

function updateBatchActionBar() {
    document.querySelectorAll('.monitor-item').forEach(item => {
        const checkbox = item.querySelector('.item-checkbox');
        item.classList.toggle('is-selected', Boolean(checkbox && checkbox.checked));
    });

    const checked = document.querySelectorAll('.item-checkbox:checked');
    const bar = document.getElementById('batchActionBar');
    const countSpan = document.getElementById('batchCount');
    if (!bar || !countSpan) return;

    const count = checked.length;
    countSpan.innerText = count;
    updateBatchFlipLabel();

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
    const csvFileInput = document.getElementById('csvImportFile');
    if (csvFileInput) {
        csvFileInput.addEventListener('change', () => previewCsvImport(csvFileInput.files[0]));
    }
    const csvForm = document.getElementById('csvImportForm');
    if (csvForm) {
        csvForm.addEventListener('submit', event => {
            if (!csvFileInput || !csvFileInput.files.length || document.getElementById('csvImportSubmit').disabled) {
                event.preventDefault();
                return;
            }
            document.getElementById('csvImportSubmit').disabled = true;
            document.getElementById('csvImportSubmit').textContent = document.documentElement.lang === 'ko' ? '가져오는 중…' : 'Importing…';
        });
    }
    const autoRefreshToggle = document.getElementById('autoRefreshToggle');
    if (autoRefreshToggle) {
        const saved = sessionStorage.getItem('webmon_auto_refresh') !== 'false';
        autoRefreshToggle.checked = saved;
        if (saved) {
            startAutoRefresh();
        }
    }
    
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

    copyTextToClipboard(cmd, button);
}

function copyUrl(button) {
    const url = button.getAttribute('data-url');
    if (!url) return;

    copyTextToClipboard(url, button);
}

function copyTextToClipboard(text, button) {
    const onSuccess = () => {
        const icon = button ? button.querySelector('i') : null;
        if (icon) {
            const originalClass = icon.className;
            icon.className = 'fas fa-check text-green-500 text-[10px]';
            setTimeout(() => {
                icon.className = originalClass;
            }, 1000);
        }
        
        showToast(document.documentElement.lang === 'ko' ? '클립보드에 복사되었습니다.' : 'Copied to clipboard!');
    };

    const onFailure = error => {
        console.error('Clipboard copy failed:', error);
        showToast(document.documentElement.lang === 'ko' ? '복사에 실패했습니다.' : 'Copy failed.', 'error');
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text)
            .then(onSuccess)
            .catch(error => {
                console.warn('Modern clipboard API failed, trying fallback...', error);
                try {
                    if (!fallbackCopy(text, onSuccess)) throw new Error('Clipboard fallback was unavailable');
                } catch (fallbackError) {
                    onFailure(fallbackError);
                }
            });
    } else {
        try {
            if (!fallbackCopy(text, onSuccess)) throw new Error('Clipboard fallback was unavailable');
        } catch (error) {
            onFailure(error);
        }
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
            throw new Error('Fallback copy command was rejected');
        }
    } catch (err) {
        document.body.removeChild(textArea);
        throw err;
    }
    
    document.body.removeChild(textArea);
    return true;
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

async function testNotification(channel, button) {
    const form = document.querySelector('#settingsModal form');
    const status = document.getElementById(`${channel}TestStatus`);
    if (!form || !status || button.disabled) return;

    const formData = new FormData(form);
    const recipientInput = document.getElementById('emailTestRecipient');
    const senderInput = document.getElementById('globalSenderEmail');
    const data = {
        channel,
        tg_token: formData.get('tg_token'),
        tg_chat_id: formData.get('tg_chat_id'),
        recipient: recipientInput ? recipientInput.value : '',
        sender: senderInput ? senderInput.value : ''
    };

    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = document.documentElement.lang === 'ko' ? '테스트 중…' : 'Testing…';
    status.textContent = '';
    status.classList.remove('text-emerald-600', 'text-amber-600', 'text-red-500');

    try {
        const response = await fetch('test_notification.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || `HTTP ${response.status}`);
        }
        status.textContent = result.message;
        status.classList.add(result.delivery_status === 'queued' ? 'text-amber-600' : 'text-emerald-600');
    } catch (error) {
        status.textContent = error.message || (document.documentElement.lang === 'ko' ? '테스트 발송에 실패했습니다.' : 'Test delivery failed.');
        status.classList.add('text-red-500');
    } finally {
        button.disabled = false;
        button.textContent = originalText;
    }
}

/**
 * 개별 아이템 즉시 재진단 및 만료일 동기화
 */
async function diagnoseItem(no, button) {
    const spinner = button.matches('i') ? button : button.querySelector('i');
    if (spinner && spinner.classList.contains('fa-spin')) return;
    if (spinner) spinner.classList.add('fa-spin');
    
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
            if (spinner) spinner.classList.remove('fa-spin');
        }, 1000);
    }
}

// 카드 뒷면 응답 그래프 (Chart.js 없이 canvas 직접 렌더링, 열 때만 로드)
const flipChartCache = new Map();
const flipChartState = new Map();
const FLIP_CACHE_MS = 30000;

function flipCard(no, force) {
    const item = document.querySelector(`.monitor-item[data-monitor-id="${no}"]`);
    if (!item) return;
    const opening = typeof force === 'boolean' ? force : !item.classList.contains('is-flipped');
    if (opening === item.classList.contains('is-flipped')) return;
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const swap = () => {
        item.classList.toggle('is-flipped', opening);
        updateBatchFlipLabel();
        const back = item.querySelector('.monitor-card-back');
        if (back) back.setAttribute('aria-hidden', String(!opening));
        if (opening) loadFlipChart(no);
    };
    if (reduce || !item.animate) { swap(); return; }
    const half = 140;
    const out = item.animate([{ transform: 'scaleX(1)' }, { transform: 'scaleX(0.02)' }], { duration: half, easing: 'ease-in' });
    out.onfinish = () => {
        swap();
        item.animate([{ transform: 'scaleX(0.02)' }, { transform: 'scaleX(1)' }], { duration: half, easing: 'ease-out' });
    };
}

function setFlipRange(no, range) {
    const item = document.querySelector(`.monitor-item[data-monitor-id="${no}"]`);
    if (!item) return;
    item.querySelectorAll('.flip-range-btn').forEach(b => b.classList.toggle('is-active', b.dataset.range === range));
    flipChartState.set(no, range);
    loadFlipChart(no);
}

async function loadFlipChart(no, force = false) {
    const item = document.querySelector(`.monitor-item[data-monitor-id="${no}"]`);
    if (!item) return;
    const range = flipChartState.get(no) || '1h';
    const msg = item.querySelector('.flip-chart-msg');
    const key = `${no}:${range}`;
    const cached = flipChartCache.get(key);
    if (cached && !force && Date.now() - cached.at < FLIP_CACHE_MS) {
        drawFlipChart(item, cached.data, range);
        return;
    }
    if (msg && !cached) msg.textContent = document.documentElement.lang === 'ko' ? '불러오는 중...' : 'Loading...';
    try {
        const res = await fetch(`get_chart_data.php?no=${no}&range=${range}&max=${range === '1h' ? 60 : 96}`, { cache: 'no-store' });
        if (!res.ok) throw new Error(res.status);
        const data = await res.json();
        flipChartCache.set(key, { at: Date.now(), data });
        if (flipChartState.get(no) === range || !flipChartState.has(no)) drawFlipChart(item, data, range);
    } catch (e) {
        if (msg) msg.textContent = document.documentElement.lang === 'ko' ? '그래프를 불러오지 못했습니다.' : 'Failed to load graph.';
    }
}

function drawFlipChart(item, data, range) {
    const canvas = item.querySelector('.flip-chart');
    const msg = item.querySelector('.flip-chart-msg');
    if (!canvas || !Array.isArray(data)) return;
    const isKo = document.documentElement.lang === 'ko';
    const set = (k, v) => { const el = item.querySelector(`[data-stat="${k}"]`); if (el) el.textContent = v; };
    if (!data.length) {
        if (msg) msg.textContent = isKo ? '표시할 기록이 없습니다.' : 'No records.';
        ['avg', 'max', 'rate', 'fail'].forEach(k => set(k, '-'));
        const c = canvas.getContext('2d'); c.clearRect(0, 0, canvas.width, canvas.height);
        return;
    }
    if (msg) msg.textContent = '';

    let samples = 0, fails = 0, sum = 0, max = 0;
    data.forEach(d => {
        const n = d.sample_count || 1;
        samples += n; fails += d.failure_count || 0; sum += d.response_time * n; if (d.response_time > max) max = d.response_time;
    });
    set('avg', (sum / samples).toFixed(2) + 's');
    set('max', max.toFixed(2) + 's');
    set('rate', (((samples - fails) / samples) * 100).toFixed(1) + '%');
    set('fail', String(fails));

    const rect = { width: canvas.clientWidth, height: canvas.clientHeight };
    const dpr = window.devicePixelRatio || 1;
    canvas.width = Math.max(1, Math.round(rect.width * dpr));
    canvas.height = Math.max(1, Math.round(rect.height * dpr));
    const ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    const W = rect.width, H = rect.height;
    const css = getComputedStyle(document.documentElement);
    const sub = css.getPropertyValue('--text-sub').trim() || '#64748b';
    const border = css.getPropertyValue('--border-color').trim() || '#e2e8f0';
    const padL = 30, padR = 6, padT = 6, padB = 16;
    const pw = W - padL - padR, ph = H - padT - padB;
    const top = Math.max(max * 1.15, 0.1);
    const x = i => padL + (data.length === 1 ? pw / 2 : (i / (data.length - 1)) * pw);
    const y = v => padT + ph - (v / top) * ph;

    ctx.clearRect(0, 0, W, H);
    ctx.font = '10px sans-serif'; ctx.fillStyle = sub; ctx.strokeStyle = border; ctx.lineWidth = 1;
    ctx.textAlign = 'right'; ctx.textBaseline = 'middle';
    [0, 0.5, 1].forEach(t => {
        const v = top * t, yy = y(v);
        ctx.beginPath(); ctx.moveTo(padL, yy); ctx.lineTo(W - padR, yy); ctx.stroke();
        ctx.fillText(v.toFixed(v >= 10 ? 0 : 1), padL - 4, yy);
    });
    const fmt = s => (s || '').slice(range === '1h' ? 11 : 11, 16);
    ctx.textBaseline = 'top'; ctx.textAlign = 'left'; ctx.fillText(fmt(data[0].time), padL, H - padB + 3);
    ctx.textAlign = 'right'; ctx.fillText(fmt(data[data.length - 1].time), W - padR, H - padB + 3);

    const grad = ctx.createLinearGradient(0, padT, 0, padT + ph);
    grad.addColorStop(0, 'rgba(24,174,231,0.28)'); grad.addColorStop(1, 'rgba(24,174,231,0)');
    ctx.beginPath();
    data.forEach((d, i) => i ? ctx.lineTo(x(i), y(d.response_time)) : ctx.moveTo(x(i), y(d.response_time)));
    ctx.strokeStyle = '#18aee7'; ctx.lineWidth = 1.6; ctx.lineJoin = 'round'; ctx.stroke();
    ctx.lineTo(x(data.length - 1), padT + ph); ctx.lineTo(x(0), padT + ph); ctx.closePath();
    ctx.fillStyle = grad; ctx.fill();

    canvas._geo = { data, padL, pw, padT, ph, top, W, H };
    bindFlipHover(canvas);

    data.forEach((d, i) => {
        if (d.result === 0) {
            ctx.beginPath(); ctx.arc(x(i), y(d.response_time), 3, 0, Math.PI * 2);
            ctx.fillStyle = '#ef4444'; ctx.fill();
        }
    });
}

function refreshFlippedCharts() {
    document.querySelectorAll('.monitor-item.is-flipped').forEach(item => loadFlipChart(Number(item.dataset.monitorId), true));
}

// 레이아웃(열 수, 창 크기) 변경 시 뒷면 그래프를 선명하게 다시 그림
if (window.ResizeObserver) {
    const flipRO = new ResizeObserver(entries => {
        entries.forEach(en => {
            const item = en.target;
            if (!item.classList.contains('is-flipped')) return;
            const no = Number(item.dataset.monitorId);
            const c = flipChartCache.get(`${no}:${flipChartState.get(no) || '1h'}`);
            if (c) drawFlipChart(item, c.data, flipChartState.get(no) || '1h');
        });
    });
    const observeFlips = () => document.querySelectorAll('.monitor-item:has(.monitor-card-back)').forEach(i => flipRO.observe(i));
    document.addEventListener('DOMContentLoaded', observeFlips);
    new MutationObserver(observeFlips).observe(document.body, { childList: true, subtree: true });
}

// 선택한 카드를 한 번에 뒤집기: 하나라도 앞면이면 모두 그래프로, 모두 뒷면이면 모두 카드로
function selectedCardItems() {
    return [...document.querySelectorAll('.item-checkbox:checked')]
        .map(cb => cb.closest('.monitor-item'))
        .filter(item => item && item.querySelector('.monitor-card-back') && item.querySelector('.monitor-card-body') && item.offsetParent !== null);
}

function flipSelectedCards() {
    const items = selectedCardItems();
    if (!items.length) return;
    const opening = items.some(i => !i.classList.contains('is-flipped'));
    items.forEach((item, idx) => setTimeout(() => flipCard(Number(item.dataset.monitorId), opening), Math.min(idx, 12) * 40));
}

function updateBatchFlipLabel() {
    const btn = document.getElementById('batchFlipBtn');
    if (!btn) return;
    const items = selectedCardItems();
    const allFlipped = items.length > 0 && items.every(i => i.classList.contains('is-flipped'));
    btn.title = allFlipped ? btn.dataset.labelClose : btn.dataset.labelOpen;
    btn.classList.toggle('bg-[#18aee7]', allFlipped);
    btn.classList.toggle('!text-white', allFlipped);
    btn.classList.toggle('hidden', items.length === 0);
}

function clearSelection() {
    document.querySelectorAll('.item-checkbox:checked').forEach(cb => { cb.checked = false; });
    const all = document.getElementById('selectAll');
    if (all) all.checked = false;
    updateBatchActionBar();
}

document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    const tag = (e.target.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
    if (document.querySelector('.item-checkbox:checked')) clearSelection();
});

// 그래프 위에 마우스를 올리면 해당 시각의 응답 값을 툴팁으로 표시
function bindFlipHover(canvas) {
    if (canvas._hoverBound) return;
    canvas._hoverBound = true;
    const wrap = canvas.parentElement;
    const tip = document.createElement('div');
    tip.className = 'flip-tip';
    const dot = document.createElement('div');
    dot.className = 'flip-dot';
    wrap.append(dot, tip);
    const hide = () => { tip.style.display = 'none'; dot.style.display = 'none'; };
    canvas.addEventListener('mouseleave', hide);
    canvas.addEventListener('mousemove', e => {
        const g = canvas._geo;
        if (!g || !g.data.length) return hide();
        const r = canvas.getBoundingClientRect();
        const mx = e.clientX - r.left;
        const n = g.data.length;
        const idx = n === 1 ? 0 : Math.max(0, Math.min(n - 1, Math.round(((mx - g.padL) / g.pw) * (n - 1))));
        const d = g.data[idx];
        const px = g.padL + (n === 1 ? g.pw / 2 : (idx / (n - 1)) * g.pw);
        const py = g.padT + g.ph - (d.response_time / g.top) * g.ph;
        const ko = document.documentElement.lang === 'ko';
        const failed = d.result === 0 || (d.failure_count || 0) > 0;
        tip.innerHTML = `<b>${(d.time || '').slice(5, 16).replace('-', '/')}</b><br>${d.response_time.toFixed(2)}s` +
            (failed ? ` <span style="color:#f87171">${ko ? '실패' : 'Failed'}${d.failure_count > 1 ? ' ' + d.failure_count : ''}</span>` : '');
        tip.style.display = 'block';
        dot.style.display = 'block';
        dot.style.left = px + 'px';
        dot.style.top = py + 'px';
        const tw = tip.offsetWidth;
        tip.style.left = Math.max(0, Math.min(g.W - tw, px - tw / 2)) + 'px';
        tip.style.top = Math.max(0, py - tip.offsetHeight - 10) + 'px';
    });
}
