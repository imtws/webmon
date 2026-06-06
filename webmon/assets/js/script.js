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
    window.location.href = url.href;
}

function filterByGroup(groupName) {
    const groupSelect = document.getElementById('searchGroup');
    if (groupSelect) groupSelect.value = groupName;
    triggerSearch();
}

function applyType(type) {
    const url = new URL(window.location.href);
    url.searchParams.set('type', type);
    window.location.href = url.href;
}

// 전체 선택 체크박스
const selectAllBtn = document.getElementById('selectAll');
if (selectAllBtn) {
    selectAllBtn.addEventListener('change', function(e) {
        document.querySelectorAll('.item-checkbox').forEach(cb => {
            cb.checked = e.target.checked;
        });
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

// 모달창 열기 및 데이터 세팅
function openModal(type, data = null) {
    const modal = document.getElementById('addModal');
    const form = document.getElementById('modalForm');

    // [추가된 부분] 현재 검색/필터 쿼리 스트링 유지
    const currentQueryField = document.getElementById('modalCurrentQuery');
    if (currentQueryField) {
        currentQueryField.value = window.location.search.replace('?', '');
    }

    // 모달 애니메이션
    if (modal) {
        modal.classList.remove('hidden');
        const modalContent = modal.querySelector('.modal-content');
        if (modalContent) {
            modalContent.style.transform = 'scale(0.95)';
            modalContent.style.opacity = '0';
            setTimeout(() => {
                modalContent.style.transition = 'all 0.2s ease-out';
                modalContent.style.transform = 'scale(1)';
                modalContent.style.opacity = '1';
            }, 10);
        }
    }

    if (document.getElementById('modalTitle')) {
        document.getElementById('modalTitle').innerText = type === 'add' ? (typeof langPack !== 'undefined' ? langPack.modalAddTitle : 'Add Monitor') : (typeof langPack !== 'undefined' ? langPack.modalEditTitle : 'Edit Monitor');
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

    } else {
        if (form) form.reset();
        if (document.getElementById('modalAction')) document.getElementById('modalAction').value = 'add';
        if (document.getElementById('modalComment')) document.getElementById('modalComment').value = '';
        if (document.getElementById('modalEmailMode')) document.getElementById('modalEmailMode').value = 'none';
        if (document.getElementById('modalSenderEmail')) document.getElementById('modalSenderEmail').value = '';
        if (document.getElementById('modalReceiverEmail')) document.getElementById('modalReceiverEmail').value = '';
        if (document.getElementById('modalRetry')) document.getElementById('modalRetry').value = '1';
        if (document.getElementById('modalTimeout')) document.getElementById('modalTimeout').value = '2';
        
        const engineRadio = document.querySelector('input[name="engine_type"][value="lynx"]');
        if (engineRadio) engineRadio.checked = true;

        if (document.getElementById('modalGroupSelect')) {
            document.getElementById('modalGroupSelect').selectedIndex = 0;
            toggleGroupNewInput();
        }
    }
}

function closeModal() {
    const modal = document.getElementById('addModal');
    if (modal) {
        const modalContent = modal.querySelector('.modal-content');
        if (modalContent) {
            modalContent.style.transform = 'scale(0.95)';
            modalContent.style.opacity = '0';
        }
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 200);
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

function setCookie(name, value, days) {
    let expires = "";
    if (days) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + (value || "")  + expires + "; path=/";
}

function toggleViewMode(mode) {
    setCookie('webmon_view', mode, 365);
    location.reload(); 
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
    const msg = typeof langPack !== 'undefined' && langPack.msg_confirm_del ? langPack.msg_confirm_del : '정말 삭제하시겠습니까?';
    if (confirm(`[ ${company} ]\n\n${msg}`)) {
        // [수정된 부분] 현재 검색/필터 쿼리 스트링 유지
        const currentQuery = window.location.search.replace('?', '');
        location.href = `website_status.php?action=delete&no=${no}&current_query=${encodeURIComponent(currentQuery)}`;
    }
}

function confirmBulkDeleteSubmit() {
    const checked = document.querySelectorAll('.item-checkbox:checked');
    if (checked.length === 0) {
        alert(typeof langPack !== 'undefined' && langPack.selDel ? langPack.selDel : '삭제할 항목을 선택해주세요.');
        return;
    }
    const companies = Array.from(checked).map(cb => cb.getAttribute('data-company')).join(', ');
    const baseMsg = typeof langPack !== 'undefined' && langPack.msg_confirm_del ? langPack.msg_confirm_del : '선택한 항목을 정말 삭제하시겠습니까?';
    if (confirm(`[ ${companies} ]\n\n${baseMsg}`)) {
        const ids = Array.from(checked).map(cb => cb.value).join(',');
        document.getElementById('bulkAction').value = 'bulk_delete';
        document.getElementById('bulkIds').value = ids;
        
        // [추가된 부분] 일괄 삭제 시 현재 쿼리 유지
        const bulkQueryField = document.getElementById('bulkCurrentQuery');
        if (bulkQueryField) bulkQueryField.value = window.location.search.replace('?', '');
        
        document.getElementById('bulkForm').submit();
    }
}

function confirmBulkStatusUpdate(status) {
    const checked = document.querySelectorAll('.item-checkbox:checked');
    if (checked.length === 0) {
        alert(typeof langPack !== 'undefined' && langPack.selStatus ? langPack.selStatus : '상태를 변경할 항목을 선택해주세요.');
        return;
    }
    let statusName = status;
    if(status === 'alarm') statusName = typeof langPack !== 'undefined' && langPack.statusAlarm ? langPack.statusAlarm : '활성화';
    if(status === 'no alarm') statusName = typeof langPack !== 'undefined' && langPack.statusNoAlarm ? langPack.statusNoAlarm : '무음';
    if(status === 'disable') statusName = typeof langPack !== 'undefined' && langPack.statusDisable ? langPack.statusDisable : '비활성화';

    const companies = Array.from(checked).map(cb => cb.getAttribute('data-company')).join(', ');
    if (confirm(`[ ${companies} ]\n\n위 항목들의 알람 상태를 '${statusName}'(으)로 변경하시겠습니까?`)) {
        const ids = Array.from(checked).map(cb => cb.value).join(',');
        document.getElementById('bulkAction').value = 'bulk_status';
        document.getElementById('bulkStatusValue').value = status;
        document.getElementById('bulkIds').value = ids;
        
        // [추가된 부분] 일괄 상태 변경 시 현재 쿼리 유지
        const bulkQueryField = document.getElementById('bulkCurrentQuery');
        if (bulkQueryField) bulkQueryField.value = window.location.search.replace('?', '');
        
        document.getElementById('bulkForm').submit();
    }
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
