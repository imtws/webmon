// WebMon Dashboard Settings, Schedule & Modals

function switchSettingsTab(tab) {
    const btnSys = document.getElementById('tabBtn-sys');
    const btnDef = document.getElementById('tabBtn-def');
    const contentSys = document.getElementById('tabContent-sys');
    const contentDef = document.getElementById('tabContent-def');
    if (!btnSys || !btnDef || !contentSys || !contentDef) return;
    
    if (tab === 'sys') {
        btnSys.classList.add('border-[#18aee7]', 'text-[#18aee7]');
        btnSys.classList.remove('border-transparent', 'text-[var(--text-sub)]');
        btnDef.classList.add('border-transparent', 'text-[var(--text-sub)]');
        btnDef.classList.remove('border-[#18aee7]', 'text-[#18aee7]');
        
        contentSys.classList.remove('hidden');
        contentDef.classList.add('hidden');
    } else {
        btnDef.classList.add('border-[#18aee7]', 'text-[#18aee7]');
        btnDef.classList.remove('border-transparent', 'text-[var(--text-sub)]');
        btnSys.classList.add('border-transparent', 'text-[var(--text-sub)]');
        btnSys.classList.remove('border-[#18aee7]', 'text-[#18aee7]');
        
        contentDef.classList.remove('hidden');
        contentSys.classList.add('hidden');
    }
}

function openSettingsModal() {
    openSideSheet('settingsModal');
    toggleAlertFormatTemplates();
    toggleSettingsAccordion('tg', true);
    toggleSettingsAccordion('defmon', true);
}

function closeSettingsModal() {
    closeSideSheet('settingsModal');
}

function toggleSettingsAccordion(id, forceOpen = null) {
    const body = document.getElementById('set-body-' + id);
    const arrow = document.getElementById('set-arrow-' + id);
    if (body && arrow) {
        const isHidden = body.classList.contains('hidden');
        const shouldOpen = forceOpen !== null ? forceOpen : isHidden;
        if (shouldOpen) {
            body.classList.remove('hidden');
            arrow.classList.add('rotate-180');
        } else {
            body.classList.add('hidden');
            arrow.classList.remove('rotate-180');
        }
    }
}

function toggleAlertFormatTemplates() {
    const formatSelect = document.getElementById('alertFormatSelect');
    const htmlFields = document.getElementById('htmlTemplateFields');
    const plainFields = document.getElementById('plainTemplateFields');
    if (formatSelect) {
        if (formatSelect.value === 'html') {
            if (htmlFields) htmlFields.classList.remove('hidden');
            if (plainFields) plainFields.classList.add('hidden');
        } else {
            if (htmlFields) htmlFields.classList.add('hidden');
            if (plainFields) plainFields.classList.remove('hidden');
        }
    }
}

// 🔔 개별 모니터링 알람 일시 무음 설정 모달
function openScheduleModal(event, row) {
    if (event) event.stopPropagation();
    const itemNo = document.getElementById('schedItemNo');
    const title = document.getElementById('schedModalTitle');
    const enabledChk = document.getElementById('schedEnabled');
    const muteUntilInput = document.getElementById('schedMuteUntil');
    if (!itemNo || !title || !enabledChk || !muteUntilInput) return;

    itemNo.value = row.no;
    title.innerText = `${row.company} 알람 일시 무음 설정`;
    
    const enabled = parseInt(row.sched_enabled || 0, 10) === 1;
    enabledChk.checked = enabled;
    
    const now = new Date();
    const offsetMs = now.getTimezoneOffset() * 60 * 1000;
    
    let muteTime = null;
    if (row.mute_until) {
        const temp = new Date(row.mute_until.replace(' ', 'T'));
        if (temp > now) {
            muteTime = temp;
        }
    }
    
    if (!muteTime) {
        const defHours = typeof defaultSettings !== 'undefined' ? parseInt(defaultSettings.muteHours || 2, 10) : 2;
        muteTime = new Date(now.getTime() + (defHours * 60 * 60 * 1000));
    }
    
    const localISOTime = new Date(muteTime.getTime() - offsetMs).toISOString().substring(0, 16);
    muteUntilInput.value = localISOTime;
    
    toggleSchedFields(enabled);
    openSideSheet('itemScheduleModal');
}

function closeItemScheduleModal() {
    closeSideSheet('itemScheduleModal');
}

function toggleSchedFields(enabled) {
    const fields = document.getElementById('schedFields');
    if (fields) {
        if (enabled) {
            fields.style.opacity = '1';
            fields.style.pointerEvents = 'auto';
        } else {
            fields.style.opacity = '0.5';
            fields.style.pointerEvents = 'none';
        }
    }
}

// 📜 작업 이력 모달 (비동기 지연 로딩)
let historyDataLoaded = false;
async function openHistoryModal(forceRefresh = false) {
    openSideSheet('historyModal');
    const container = document.getElementById('historyModalContent');
    if (!container) return;

    if (!historyDataLoaded || forceRefresh) {
        container.innerHTML = `
            <div class="flex flex-col items-center justify-center py-16 text-center text-[var(--text-sub)]">
                <i class="fas fa-circle-notch fa-spin text-3xl text-[#18aee7] mb-3"></i>
                <p class="text-sm font-bold">${document.documentElement.lang === 'ko' ? '작업 이력 목록을 불러오는 중...' : 'Loading job history...'}</p>
            </div>`;
        try {
            const res = await fetch('get_history.php');
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const html = await res.text();
            container.innerHTML = html;
            historyDataLoaded = true;
        } catch (err) {
            console.error('Failed to load history:', err);
            container.innerHTML = `
                <div class="text-center py-12 text-red-500 font-bold">
                    <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                    <p>${document.documentElement.lang === 'ko' ? '이력을 불러오지 못했습니다. 다시 시도해 주세요.' : 'Failed to load history. Please try again.'}</p>
                    <button type="button" onclick="openHistoryModal(true)" class="mt-3 px-4 py-1.5 bg-[#18aee7] text-white rounded-lg text-xs font-bold">${document.documentElement.lang === 'ko' ? '다시 시도' : 'Retry'}</button>
                </div>`;
        }
    }
}

function closeHistoryModal() {
    closeSideSheet('historyModal');
}

// 🌐 URL 쿼리 파라미터 기반 알림 및 모달 자동 오픈 핸들러
window.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const isKo = document.documentElement.lang === 'ko';

    if (urlParams.has('settings_saved')) {
        const msg = isKo ? '설정이 성공적으로 저장되었습니다.' : 'Settings saved successfully.';
        showToast(msg);
        const newUrl = window.location.pathname + window.location.search.replace(/[?&]settings_saved=1/, '').replace(/^&/, '?').replace(/\?$/, '');
        window.history.replaceState({}, document.title, newUrl);
    }
    if (urlParams.has('settings_error')) {
        const err = urlParams.get('settings_error');
        let msg = isKo ? '설정 저장 중 오류가 발생했습니다.' : 'An error occurred while saving settings.';
        if (err === 'sender_email_invalid') {
            msg = isKo ? '기본 발송 이메일 주소 형식을 확인해 주세요.' : 'Enter a valid default sender email address.';
        }
        showToast(msg, 'error');
        const newUrl = window.location.pathname + window.location.search.replace(/[?&]settings_error=[^&]+/, '').replace(/^&/, '?').replace(/\?$/, '');
        window.history.replaceState({}, document.title, newUrl);
    }

    if (urlParams.has('msg')) {
        const msgType = urlParams.get('msg');
        let msgText = '';
        
        switch(msgType) {
            case 'add_success':
                msgText = isKo ? '새로운 모니터링 항목이 추가되었습니다.' : 'New monitor item added successfully.';
                break;
            case 'edit_success':
                msgText = isKo ? '모니터링 설정이 변경되었습니다.' : 'Monitor settings updated successfully.';
                break;
            case 'delete_success':
                msgText = isKo ? '모니터링 항목이 삭제되었습니다.' : 'Monitor item deleted successfully.';
                break;
            case 'bulk_delete_success':
                msgText = isKo ? '선택한 항목들이 모두 삭제되었습니다.' : 'Selected items deleted successfully.';
                break;
            case 'bulk_status_success':
                msgText = isKo ? '알람 상태가 일괄 업데이트되었습니다.' : 'Alarm status updated successfully.';
                break;
            case 'bulk_edit_success':
                msgText = isKo ? '선택한 항목들의 설정이 일괄 변경되었습니다.' : 'Selected items settings updated in bulk successfully.';
                break;
            case 'csv_import_success': {
                const added = Number(urlParams.get('added') || 0);
                const skipped = Number(urlParams.get('skipped') || 0);
                msgText = isKo
                    ? `CSV 가져오기 완료: ${added}개 등록, ${skipped}개 중복 건너뜀`
                    : `CSV import complete: ${added} added, ${skipped} duplicates skipped`;
                break;
            }
            case 'csv_import_error':
                msgText = (isKo ? 'CSV 가져오기 실패: ' : 'CSV import failed: ') + (urlParams.get('detail') || (isKo ? '파일을 확인해 주세요.' : 'Please check the file.'));
                break;
            case 'invalid_schedule':
                msgText = isKo ? '무음 종료 시각을 현재보다 뒤로 지정해 주세요.' : 'Choose a mute end time in the future.';
                break;
            case 'schedule_success':
                msgText = isKo ? '알람 무음 스케줄이 성공적으로 저장되었습니다.' : 'Mute schedule saved successfully.';
                break;
            case 'rollback_success':
                msgText = isKo ? '작업이 성공적으로 원복되었습니다.' : 'Job rolled back successfully.';
                break;
            case 'rollback_failed':
                msgText = isKo ? '작업 원복에 실패했습니다.' : 'Failed to roll back job.';
                break;
        }
        
        if (msgText) {
            showToast(msgText, ['invalid_schedule', 'csv_import_error'].includes(msgType) ? 'error' : undefined);
            const cleanUrl = new URL(window.location.href);
            ['msg', 'detail', 'added', 'skipped'].forEach(param => cleanUrl.searchParams.delete(param));
            const newUrl = cleanUrl.pathname + cleanUrl.search + cleanUrl.hash;
            window.history.replaceState({}, document.title, newUrl);
        }
    }

    if (urlParams.has('expand')) {
        const expandNo = urlParams.get('expand');
        setTimeout(() => {
            if (typeof toggleAccordion === 'function') {
                toggleAccordion(null, expandNo);
            }
        }, 300);
    }

    if (urlParams.has('settings')) {
        setTimeout(() => {
            openSettingsModal();
        }, 300);
    }
});
