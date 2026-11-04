<div id="csvImportModal" class="side-sheet-overlay hidden fixed inset-0 z-[1100] justify-end bg-black/40 backdrop-blur-sm">
    <div class="side-sheet-panel flex h-full w-full max-w-3xl flex-col overflow-hidden border-l border-[var(--border-color)] bg-[var(--card-bg)] shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="csvImportTitle">
        <h2 id="csvImportTitle" class="sheet-header">
            <span class="sheet-header-title"><i class="fas fa-file-csv"></i><span><?= $current_lang == 'KO' ? 'CSV로 모니터링 항목 가져오기' : 'Import Monitors from CSV' ?></span></span>
            <button type="button" class="sheet-close-button" aria-label="<?= $current_lang == 'KO' ? '닫기' : 'Close' ?>" onclick="closeCsvImportModal()"><i class="fas fa-times"></i></button>
        </h2>
        <form id="csvImportForm" method="POST" action="website_status.php" enctype="multipart/form-data" class="sheet-form flex min-h-0 flex-1 flex-col overflow-hidden">
            <input type="hidden" name="action" value="csv_import">
            <input type="hidden" name="current_query" value="<?= htmlspecialchars(http_build_query($_GET)) ?>">
            <div class="min-h-0 flex-1 space-y-5 overflow-y-auto p-5 style-scrollbar">
                <div class="rounded-2xl border border-[#18aee7]/20 bg-[#18aee7]/5 p-4">
                    <p class="text-sm font-bold text-[var(--text-main)]"><?= $current_lang == 'KO' ? 'CSV 파일을 선택하고 등록 전 내용을 확인하세요.' : 'Choose a CSV file and review the rows before importing.' ?></p>
                    <p class="mt-1 text-xs leading-5 text-[var(--text-sub)]"><?= $current_lang == 'KO' ? '필수 열: company, url, contents. 선택 열: group_name, check, ssl_mon, dom_mon, comment, engine_type, retry_count, timeout_sec, email_mode, sender_email, receiver_email. 기존 URL과 중복된 행은 건너뛰며 기존 설정은 변경하지 않습니다. 최대 500행, 파일 크기 5MB까지 지원합니다.' : 'Required columns: company, url, contents. Optional: group_name, check, ssl_mon, dom_mon, comment, engine_type, retry_count, timeout_sec, email_mode, sender_email, receiver_email. Existing URLs are skipped and never overwritten. Up to 500 rows and 5 MB.' ?></p>
                    <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2">
                        <a href="csv_template.php" class="inline-flex items-center gap-2 text-xs font-bold text-[#18aee7] hover:underline">
                            <i class="fas fa-download"></i><?= $current_lang == 'KO' ? 'CSV 템플릿 다운로드' : 'Download CSV template' ?>
                        </a>
                        <a href="csv_export.php" class="inline-flex items-center gap-2 text-xs font-bold text-[#18aee7] hover:underline">
                            <i class="fas fa-file-export"></i><?= $current_lang == 'KO' ? '현재 항목 CSV 저장' : 'Export current monitors' ?>
                        </a>
                    </div>
                    <p class="mt-2 text-[10px] leading-4 text-[var(--text-sub)]"><?= $current_lang == 'KO' ? '현재 등록된 전체 항목을 내보냅니다. SMTP 비밀번호 등 비밀 정보와 모니터링 이력은 포함하지 않습니다.' : 'Exports all registered monitors. SMTP passwords, other secrets, and monitoring history are not included.' ?></p>
                </div>
                <div>
                    <label for="csvImportFile" class="mb-2 block text-xs font-bold text-[var(--text-sub)]"><?= $current_lang == 'KO' ? '가져올 CSV 파일' : 'CSV file' ?></label>
                    <input type="file" id="csvImportFile" name="csv_file" accept=".csv,text/csv" required class="block w-full rounded-xl border border-[var(--border-color)] bg-[var(--bg-color)] p-3 text-sm text-[var(--text-main)] file:mr-3 file:rounded-lg file:border-0 file:bg-[#18aee7]/10 file:px-3 file:py-2 file:text-xs file:font-bold file:text-[#18aee7]">
                </div>
                <div id="csvPreviewStatus" class="hidden rounded-xl border border-[var(--border-color)] bg-[var(--bg-color)] p-3 text-xs text-[var(--text-sub)]" role="status" aria-live="polite"></div>
                <div id="csvPreviewWrap" class="hidden overflow-hidden rounded-xl border border-[var(--border-color)]">
                    <div class="border-b border-[var(--border-color)] px-4 py-3 text-xs font-bold text-[var(--text-main)]" id="csvPreviewCount"></div>
                    <div class="max-h-80 overflow-auto">
                        <table class="w-full min-w-[680px] border-collapse text-left text-xs">
                            <thead class="sticky top-0 bg-[var(--bg-color)] text-[var(--text-sub)]">
                                <tr>
                                    <th class="p-3"><?= $current_lang == 'KO' ? '행' : 'Row' ?></th>
                                    <th class="p-3"><?= $current_lang == 'KO' ? '회사명' : 'Company' ?></th>
                                    <th class="p-3">URL</th>
                                    <th class="p-3"><?= $current_lang == 'KO' ? '키워드' : 'Keyword' ?></th>
                                    <th class="p-3"><?= $current_lang == 'KO' ? '그룹' : 'Group' ?></th>
                                </tr>
                            </thead>
                            <tbody id="csvPreviewBody" class="divide-y divide-[var(--border-color)] text-[var(--text-main)]"></tbody>
                        </table>
                    </div>
                    <p class="border-t border-[var(--border-color)] px-4 py-2 text-[10px] text-[var(--text-sub)]"><?= $current_lang == 'KO' ? '미리보기는 처음 20행까지 표시합니다. 전체 파일은 서버에서 다시 검증합니다.' : 'Showing up to 20 rows. The server validates the entire file again before importing.' ?></p>
                </div>
            </div>
            <div class="sheet-footer">
                <button type="button" onclick="closeCsvImportModal()" class="rounded-xl border border-[var(--border-color)] px-5 py-2.5 text-xs font-bold text-[var(--text-main)] hover:bg-[var(--bg-color)]"><?= $current_lang == 'KO' ? '취소' : 'Cancel' ?></button>
                <button type="submit" id="csvImportSubmit" disabled class="rounded-xl bg-[#18aee7] px-5 py-2.5 text-xs font-bold text-white transition-colors hover:bg-[#159bd0] disabled:cursor-not-allowed disabled:opacity-40"><?= $current_lang == 'KO' ? '항목 가져오기' : 'Import monitors' ?></button>
            </div>
        </form>
    </div>
</div>
