<div id="historyModal" class="side-sheet-overlay hidden fixed inset-0 z-[1100] justify-end bg-black/45">
    <div class="side-sheet-panel modal-content flex flex-col w-full max-w-5xl h-full bg-[var(--card-bg)] shadow-2xl border-l border-[var(--border-color)] overflow-hidden" role="dialog" aria-modal="true">
        
        <!-- Header -->
        <div class="sheet-header">
            <h2 class="sheet-header-title">
                <i class="fas fa-history"></i>
                <span><?= $current_lang == 'KO' ? '작업 이력 및 원복 관리' : 'Job History & Rollback' ?></span>
            </h2>
            <button type="button" onclick="closeHistoryModal()" class="sheet-close-button" aria-label="<?= $current_lang == 'KO' ? '닫기' : 'Close' ?>">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        
        <!-- Content Body (AJAX Loaded) -->
        <div id="historyModalContent" class="sheet-form flex-1 overflow-y-auto p-5 style-scrollbar">
            <div id="historyLoadingIndicator" class="flex flex-col items-center justify-center py-16 text-center text-[var(--text-sub)]">
                <i class="fas fa-circle-notch fa-spin text-3xl text-[#18aee7] mb-3"></i>
                <p class="text-sm font-bold"><?= $current_lang == 'KO' ? '작업 이력 목록을 불러오는 중...' : 'Loading job history...' ?></p>
            </div>
        </div>
    </div>
</div>

<script>
function toggleJobDetail(id) {
    const detailRow = document.getElementById('job-detail-' + id);
    const arrow = document.getElementById('detail-arrow-' + id);
    if (detailRow && arrow) {
        if (detailRow.classList.contains('hidden')) {
            detailRow.classList.remove('hidden');
            if (arrow) arrow.classList.add('rotate-180');
        } else {
            detailRow.classList.add('hidden');
            if (arrow) arrow.classList.remove('rotate-180');
        }
    }
}

function confirmRollback(jobId, count, actionType) {
    const isKo = (document.documentElement.lang === 'ko' || '<?= $current_lang ?>' === 'KO');
    let actionLabel = actionType;
    if (actionType === 'bulk_update') actionLabel = isKo ? '일괄 수정' : 'Bulk Edit';
    else if (actionType === 'csv_import') actionLabel = isKo ? 'CSV 가져오기' : 'CSV Import';
    else if (actionType === 'bulk_delete') actionLabel = isKo ? '일괄 삭제' : 'Bulk Delete';
    else if (actionType === 'delete') actionLabel = isKo ? '삭제' : 'Delete';
    else if (actionType === 'edit') actionLabel = isKo ? '수정' : 'Edit';

    openConfirmDialog({
        title: isKo ? '작업 원복' : 'Roll back job',
        message: isKo
            ? `[${actionLabel}] 작업의 ${count}개 대상을 이전 설정으로 복원할까요?`
            : `Restore ${count} targets from this ${actionLabel} job to their previous settings?`,
        actionLabel: isKo ? '원복' : 'Roll back',
        tone: 'neutral',
        onConfirm: () => {
            window.location.href = `website_status.php?action=rollback_job&job_id=${jobId}`;
        }
    });
}
</script>
