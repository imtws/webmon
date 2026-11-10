    <div id="confirmDialog" class="fixed inset-0 z-[1300] hidden items-center justify-center bg-black/45 p-4" role="presentation">
        <div class="w-full max-w-md rounded-2xl border border-[var(--border-color)] bg-[var(--card-bg)] p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="confirmDialogTitle" aria-describedby="confirmDialogMessage">
            <div class="confirm-dialog-icon mb-4 flex h-10 w-10 items-center justify-center rounded-full bg-red-50 text-red-600 dark:bg-red-950/30 dark:text-red-400">
                <i class="fas fa-exclamation"></i>
            </div>
            <h2 id="confirmDialogTitle" class="text-lg font-bold text-[var(--text-main)]"></h2>
            <p id="confirmDialogMessage" class="mt-2 text-sm leading-6 text-[var(--text-sub)]"></p>
            <ul id="confirmDialogTargets" class="mt-4 hidden max-h-40 space-y-1 overflow-y-auto rounded-xl border border-[var(--border-color)] bg-[var(--bg-color)] p-3 text-sm text-[var(--text-main)]"></ul>
            <button id="confirmDialogExpand" type="button" class="mt-2 hidden text-xs font-semibold text-[#18aee7] hover:underline" onclick="toggleConfirmTargets()"></button>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" onclick="closeConfirmDialog()" class="rounded-xl border border-[var(--border-color)] px-4 py-2.5 text-sm font-semibold text-[var(--text-main)] hover:bg-[var(--bg-color)]">
                    <?= $current_lang == 'KO' ? '취소' : 'Cancel' ?>
                </button>
                <button id="confirmDialogAction" type="button" onclick="runConfirmedAction()" class="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700">
                    <?= $current_lang == 'KO' ? '삭제' : 'Delete' ?>
                </button>
            </div>
        </div>
    </div>

