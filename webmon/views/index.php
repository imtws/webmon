<?php
require_once __DIR__ . '/components/expiry.php';
?>
<!DOCTYPE html>
<html lang="<?= strtolower($current_lang ?? 'ko') ?>" class="<?= ($current_theme ?? 'light') === 'dark' ? 'dark' : '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $t['logo'] ?></title>
    <script>
        if (sessionStorage.getItem('webmon_auto_refresh') !== 'false') {
            document.documentElement.classList.add('live-refresh-initializing');
        }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Flatpickr (Datetime picker) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ko.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <link rel="stylesheet" href="assets/css/style.css?v=<?=@filemtime(__DIR__ . '/../assets/css/style.css') ?: '2.1'?>">
    <script>
        tailwind.config = { darkMode: 'class' };
        const langPack = <?= isset($js_lang) ? $js_lang : '{}' ?>;
        const defaultSettings = {
            emailMode: '<?= htmlspecialchars($settings['def_email_mode'] ?? 'none') ?>',
            senderEmail: '<?= htmlspecialchars(($settings['def_sender_email'] ?? '') !== '' ? $settings['def_sender_email'] : ($settings['global_sender_email'] ?? '')) ?>',
            receiverEmail: '<?= htmlspecialchars($settings['def_receiver_email'] ?? '') ?>',
            check: '<?= htmlspecialchars($settings['def_check'] ?? 'alarm') ?>',
            sslMon: '<?= htmlspecialchars($settings['def_ssl_mon'] ?? '1') ?>',
            domMon: '<?= htmlspecialchars($settings['def_dom_mon'] ?? '1') ?>',
            engineType: '<?= htmlspecialchars($settings['def_engine_type'] ?? 'lynx') ?>',
            retryCount: '<?= htmlspecialchars($settings['def_retry_count'] ?? '1') ?>',
            timeoutSec: '<?= htmlspecialchars($settings['def_timeout_sec'] ?? '2') ?>',
            muteHours: '<?= htmlspecialchars($settings['def_mute_hours'] ?? '2') ?>'
        };
    </script>
</head>
<body class="m-0 p-0 text-[var(--text-main)] bg-[var(--bg-color)]">

    <div id="headerSticky" class="sticky top-0 z-[100] w-full bg-[var(--bg-color)] shadow-sm border-b border-[var(--border-color)]">
        <div class="dashboard-header-inner">
            <div class="max-w-7xl mx-auto flex flex-col gap-4">
                <div class="dashboard-toolbar">
                    <h1 onclick="location.href='website_status.php'" class="text-xl text-[#18aee7] font-black cursor-pointer"><?= $t['logo'] ?></h1>
                    <div class="flex items-center gap-2">
                        <!-- 일괄 작업 컨트롤 바: 체크 시에만 표시 (iOS Contextual Action Bar) -->
                        <div id="batchActionBar" class="flex items-center gap-2 transition-all duration-300 opacity-0 -translate-x-2 pointer-events-none hidden">
                            <span class="text-[11px] font-black text-slate-500 dark:text-slate-400 mr-1 whitespace-nowrap"><span id="batchCount" class="text-[#18aee7]">0</span>개 선택됨</span>
                            <!-- 선택 일괄 설정: iOS Tinted Style -->
                            <button type="button" onclick="openBulkModal()" class="unified-ui px-4 whitespace-nowrap shrink-0 bg-[#18aee7]/10 dark:bg-[#18aee7]/15 hover:bg-[#18aee7]/20 dark:hover:bg-[#18aee7]/25 text-[#18aee7] font-bold transition-colors border-none rounded-xl">
                                <i class="fas fa-sliders-h mr-1.5 text-[10px]"></i><?= $current_lang == 'KO' ? '선택 일괄 설정' : 'Bulk Edit Selected' ?>
                            </button>
                            <button type="button" onclick="clearSelection()" class="unified-ui w-9 !px-0 shrink-0 bg-slate-500/10 dark:bg-slate-400/15 hover:bg-slate-500/20 text-slate-500 dark:text-slate-300 transition-colors border-none rounded-xl" title="<?= $current_lang == 'KO' ? '선택 해제 (Esc)' : 'Clear selection (Esc)' ?>" aria-label="<?= $current_lang == 'KO' ? '선택 해제' : 'Clear selection' ?>"><i class="fas fa-times text-[12px]"></i></button>
                            <button type="button" id="batchFlipBtn" onclick="flipSelectedCards()" class="unified-ui w-9 !px-0 shrink-0 bg-[#18aee7]/10 dark:bg-[#18aee7]/15 hover:bg-[#18aee7]/20 dark:hover:bg-[#18aee7]/25 text-[#18aee7] transition-colors border-none rounded-xl" title="<?= $current_lang == 'KO' ? '선택 카드 뒤집기 (최근 추이)' : 'Flip selected cards (recent trend)' ?>" aria-label="<?= $current_lang == 'KO' ? '선택 카드 뒤집기' : 'Flip selected cards' ?>" data-label-open="<?= $current_lang == 'KO' ? '선택 카드 뒤집기 (최근 추이)' : 'Flip selected cards (recent trend)' ?>" data-label-close="<?= $current_lang == 'KO' ? '선택 카드 앞면으로 돌아가기' : 'Flip selected cards back' ?>">
                                <i class="fas fa-chart-line text-[12px]"></i>
                            </button>
                            <!-- 선택 삭제: 아이콘 -->
                            <button type="button" onclick="confirmBulkDeleteSubmit()" class="unified-ui w-9 !px-0 shrink-0 bg-red-500/10 dark:bg-red-500/20 hover:bg-red-500/20 dark:hover:bg-red-500/30 text-red-600 dark:text-red-400 transition-colors border-none rounded-xl" title="<?= htmlspecialchars($t['btn_del']) ?>" aria-label="<?= htmlspecialchars($t['btn_del']) ?>"><i class="fas fa-trash-alt text-[12px]"></i></button>
                            <div class="h-4 w-[1px] bg-slate-300 dark:bg-slate-700 mx-1"></div>
                        </div>

                        <!-- 작업 이력: iOS Tinted Style -->
                        <button type="button" onclick="openHistoryModal()" class="unified-ui px-4 bg-[var(--card-bg)] border border-[var(--border-color)] hover:border-[#18aee7] hover:text-[#18aee7] font-bold transition-colors rounded-xl">
                            <i class="fas fa-history mr-1.5 text-[10px]"></i><?= $current_lang == 'KO' ? '작업 이력' : 'Job History' ?>
                        </button>
                        <!-- 추가: iOS Filled System Blue Style -->
                        <button type="button" onclick="openModal('add')" class="unified-ui px-5 bg-[#18aee7] hover:bg-[#159bd0] text-white font-bold shadow-sm transition-all border-none rounded-xl"><?= $t['btn_add'] ?></button>
                        <button type="button" onclick="openCsvImportModal()" class="unified-ui px-4 bg-[var(--card-bg)] border border-[var(--border-color)] hover:border-[#18aee7] hover:text-[#18aee7] font-bold transition-colors rounded-xl" title="<?= $current_lang == 'KO' ? 'CSV 파일로 여러 항목 가져오기' : 'Import multiple monitors from CSV' ?>">
                            <i class="fas fa-file-import mr-1.5"></i><?= $current_lang == 'KO' ? 'CSV 가져오기' : 'Import CSV' ?>
                        </button>
                        
                        <div class="flex items-center gap-2 ml-2">
                            <div class="switch-container">
                                <div onclick="toggleViewMode('card')" class="switch-btn <?= $current_view == 'card' ? 'active' : '' ?>" title="<?= $current_lang == 'KO' ? '카드 뷰' : 'Card View' ?>"><i class="fas fa-table-cells-large"></i></div>
                                <div onclick="toggleViewMode('list')" class="switch-btn <?= $current_view == 'list' ? 'active' : '' ?>" title="<?= $current_lang == 'KO' ? '리스트 뷰' : 'List View' ?>"><i class="fas fa-list"></i></div>
                                <div onclick="toggleViewMode('log')" class="switch-btn <?= $current_view == 'log' ? 'active' : '' ?>" title="<?= $current_lang == 'KO' ? '실시간 로그뷰' : 'Log View' ?>"><i class="fas fa-terminal"></i></div>
                            </div>
                            <?php if ($current_view === 'card'): ?>
                            <button type="button" onclick="toggleCardDensity()" class="card-density-toggle" title="<?= $current_lang == 'KO'
                                ? ($current_card_columns === '3' ? '카드를 더 크게 보기 · 2열로 전환' : '카드를 더 많이 보기 · 3열로 전환')
                                : ($current_card_columns === '3' ? 'Larger cards · switch to 2 columns' : 'More cards · switch to 3 columns') ?>"
                                aria-label="<?= $current_lang == 'KO'
                                    ? ($current_card_columns === '3' ? '카드를 더 크게 보기, 2열로 전환' : '카드를 더 많이 보기, 3열로 전환')
                                    : ($current_card_columns === '3' ? 'Larger cards, switch to 2 columns' : 'More cards, switch to 3 columns') ?>">
                                <i class="fas <?= $current_card_columns === '3' ? 'fa-table-cells-large' : 'fa-table-cells' ?>" aria-hidden="true"></i>
                                <span><?= $current_card_columns === '3' ? '2' : '3' ?><?= $current_lang == 'KO' ? '열' : ' col' ?></span>
                            </button>
                            <?php endif; ?>
                            <div class="switch-container">
                                <div onclick="toggleLang('KO')" class="switch-btn <?= $current_lang == 'KO' ? 'active' : '' ?>">KO</div>
                                <div onclick="toggleLang('EN')" class="switch-btn <?= $current_lang == 'EN' ? 'active' : '' ?>">EN</div>
                            </div>
                            <div class="switch-container">
                                <div onclick="toggleTheme('light')" class="switch-btn <?= $current_theme == 'light' ? 'active' : '' ?>"><i class="fas fa-sun"></i></div>
                                <div onclick="toggleTheme('dark')" class="switch-btn <?= $current_theme == 'dark' ? 'active' : '' ?>"><i class="fas fa-moon"></i></div>
                            </div>
                            <button type="button" onclick="openSettingsModal()" class="flex items-center justify-center w-8 h-8 rounded-lg bg-[var(--card-bg)] border border-[var(--border-color)] hover:border-[#18aee7] hover:text-[#18aee7] transition-all ml-1 shadow-sm" title="Settings">
                                <i class="fas fa-cog text-sm"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex justify-between items-end px-1 dashboard-status-row">
                    <!-- iOS 스타일 Segmented Control -->
                    <div class="status-tabs bg-slate-100/80 dark:bg-slate-800/80 p-1 rounded-xl flex gap-1 relative w-max max-w-full border border-slate-200/50 dark:border-slate-700/30">
                        <!-- TOTAL 탭 -->
                        <div onclick="applyType('total')" class="relative px-3 py-2 rounded-lg flex items-center gap-2 cursor-pointer transition-all duration-200 select-none z-10 <?= $type=='total' ? 'bg-white dark:bg-slate-900 shadow-sm border border-black/5 dark:border-white/5' : 'hover:bg-slate-200/40 dark:hover:bg-slate-700/20' ?>">
                            <i class="fas fa-th-list text-slate-500 dark:text-slate-400 text-xs"></i>
                            <span class="text-[12px] font-black text-slate-700 dark:text-slate-300">TOTAL</span>
                            <span id="statTotal" class="px-2 py-0.5 rounded-full text-[10px] font-black bg-blue-100 dark:bg-blue-900/30 text-[#18aee7]"><?= $stat_total ?></span>
                        </div>
                        <!-- SUCCESS 탭 -->
                        <div onclick="applyType('success')" class="relative px-3 py-2 rounded-lg flex items-center gap-2 cursor-pointer transition-all duration-200 select-none z-10 <?= $type=='success' ? 'bg-white dark:bg-slate-900 shadow-sm border border-black/5 dark:border-white/5' : 'hover:bg-slate-200/40 dark:hover:bg-slate-700/20' ?>">
                            <i class="fas fa-check-circle text-green-500 text-xs"></i>
                            <span class="text-[12px] font-black text-slate-700 dark:text-slate-300">SUCCESS</span>
                            <span id="statSuccess" class="px-2 py-0.5 rounded-full text-[10px] font-black bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400"><?= $stat_success ?></span>
                        </div>
                        <!-- FAILURE 탭 -->
                        <div onclick="applyType('failure')" class="relative px-3 py-2 rounded-lg flex items-center gap-2 cursor-pointer transition-all duration-200 select-none z-10 <?= $type=='failure' ? 'bg-white dark:bg-slate-900 shadow-sm border border-black/5 dark:border-white/5' : 'hover:bg-slate-200/40 dark:hover:bg-slate-700/20' ?>">
                            <i class="fas fa-exclamation-triangle text-red-500 text-xs"></i>
                            <span class="text-[12px] font-black text-slate-700 dark:text-slate-300">FAILURE</span>
                            <span id="statFail" class="px-2 py-0.5 rounded-full text-[10px] font-black bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400"><?= $stat_fail ?></span>
                        </div>
                        <!-- EXPIRING 탭 -->
                        <?php if (($current_view ?? 'card') !== 'log'): ?>
                        <div onclick="applyType('expiring')" class="relative px-3 py-2 rounded-lg flex items-center gap-2 cursor-pointer transition-all duration-200 select-none z-10 <?= $type=='expiring' ? 'bg-white dark:bg-slate-900 shadow-sm border border-black/5 dark:border-white/5' : 'hover:bg-slate-200/40 dark:hover:bg-slate-700/20' ?>">
                            <i class="fas fa-hourglass-half text-amber-500 text-xs"></i>
                            <span class="text-[12px] font-black text-slate-700 dark:text-slate-300">EXPIRING</span>
                            <span id="statExpiring" class="px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-500"><?= $stat_expiring ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <!-- 실행 정보 -->
                    <div class="text-[10px] font-medium text-right text-[var(--text-sub)] mb-1 dashboard-run-info">
                        <div><i class="fas fa-microchip"></i> <?= $t['last_run'] ?>: <span id="lastRunValue"><?= $last_run ?></span></div>
                        <div><i class="far fa-clock"></i> <?= $t['next_run'] ?>: <span id="nextRunValue"><?= $next_run ?></span></div>
                    </div>
                </div>

                <div class="filter-toolbar bg-slate-100/40 dark:bg-slate-800/40 border border-slate-200/50 dark:border-slate-700 rounded-xl p-2">
                    <div class="filter-toolbar-controls <?= $current_view === 'log' ? 'is-log-view' : '' ?>">
                    <div class="filter-selection-slot">
                        <?php if (isset($_GET['filter_no']) && (int)$_GET['filter_no'] > 0): ?>
                        <div class="filter-selection-notice">
                            <span><i class="fas fa-info-circle mr-1"></i><?= $current_lang == 'KO' ? '개별 항목 보기 중' : 'Single Item View' ?></span>
                            <a href="website_status.php" class="ml-2 hover:underline text-red-500 font-extrabold cursor-pointer" aria-label="<?= $current_lang == 'KO' ? '개별 항목 보기 해제' : 'Clear single-item view' ?>">✕</a>
                        </div>
                        <?php elseif ($current_view !== 'log'): ?>
                        <label class="filter-select-all">
                            <input type="checkbox" id="selectAll" class="w-4 h-4 rounded accent-[#18aee7] cursor-pointer">
                            <span><?= $t['select_all'] ?></span>
                        </label>
                        <?php endif; ?>
                    </div>

                    <!-- 그룹 필터 -->
                    <div class="filter-control filter-group-slot flex items-center">
                        <i class="fas fa-folder text-[10px] text-slate-400 dark:text-slate-500 mr-2"></i>
                        <select id="searchGroup" onchange="triggerSearch()" class="bg-transparent text-xs font-black outline-none cursor-pointer text-[var(--text-main)] dark:text-white max-w-[120px]">
                            <option value="all"><?= $t['group_all'] ?></option>
                            <?php foreach($existing_groups as $g): ?>
                                <option value="<?=htmlspecialchars($g)?>" <?= $search_group==$g?'selected':'' ?>><?=htmlspecialchars($g)?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 검색 창 -->
                    <div class="filter-control filter-search-control flex items-center min-w-0">
                        <i class="fas fa-search text-[10px] text-slate-400 dark:text-slate-500 mr-2.5"></i>
                        <input type="text" id="searchCompany" value="<?=htmlspecialchars($search_company)?>" class="w-full bg-transparent text-xs outline-none text-[var(--text-main)] dark:text-white" placeholder="<?= $t['search_company_ph'] ?>" onkeypress="if(event.keyCode==13) triggerSearch()">
                    </div>
                    <button onclick="triggerSearch()" class="filter-search-button px-5 py-2.5 bg-[#18aee7] hover:bg-[#159bd0] text-white rounded-xl text-xs font-black transition-all shadow-sm shadow-blue-500/10 whitespace-nowrap">
                        <?= $t['search_btn'] ?>
                    </button>

                    <!-- 상태 필터 -->
                    <div class="filter-control filter-status-slot flex items-center">
                        <i class="fas fa-filter text-[10px] text-slate-400 dark:text-slate-500 mr-2"></i>
                        <select id="filterStatus" onchange="triggerSearch()" class="bg-transparent text-xs font-black outline-none cursor-pointer text-[var(--text-main)] dark:text-white">
                            <option value="all" <?= $filter_status=='all'?'selected':'' ?>><?= $t['filter_all'] ?></option>
                            <option disabled>──────────</option> 
                            <option value="alarm" <?= $filter_status=='alarm'?'selected':'' ?>><?= $t['status_alarm'] ?></option>
                            <option value="no alarm" <?= $filter_status=='no alarm'?'selected':'' ?>><?= $t['status_noalarm'] ?></option>
                            <?php if ($current_view !== 'log'): ?>
                            <option value="disable" <?= $filter_status=='disable'?'selected':'' ?>><?= $t['status_disable'] ?></option>
                            <?php endif; ?>
                            <option disabled>──────────</option> 
                            <option value="timeout_custom" <?= $filter_status=='timeout_custom'?'selected':'' ?>><?= $t['filter_timeout_custom'] ?></option>
                            <option value="timeout_default" <?= $filter_status=='timeout_default'?'selected':'' ?>><?= $t['filter_timeout_default'] ?></option>
                        </select>
                    </div>

                    <!-- 엔진 필터 -->
                    <div class="filter-control filter-engine-slot flex items-center">
                        <i class="fas fa-microchip text-[10px] text-slate-400 dark:text-slate-500 mr-2"></i>
                        <select id="filterEngine" onchange="triggerSearch()" class="bg-transparent text-xs font-black outline-none cursor-pointer text-[var(--text-main)] dark:text-white">
                            <option value="all" <?= (!isset($_GET['filter_engine']) || $_GET['filter_engine'] == 'all') ? 'selected' : '' ?>><?= $current_lang == 'KO' ? '모든 엔진' : 'All Engines' ?></option>
                            <option value="lynx" <?= (isset($_GET['filter_engine']) && $_GET['filter_engine'] == 'lynx') ? 'selected' : '' ?>><?= $current_lang == 'KO' ? 'LYNX 엔진' : 'LYNX Engine' ?></option>
                            <option value="curl" <?= (isset($_GET['filter_engine']) && $_GET['filter_engine'] == 'curl') ? 'selected' : '' ?>><?= $current_lang == 'KO' ? 'CURL 엔진' : 'CURL Engine' ?></option>
                        </select>
                    </div>

                    <div class="filter-sort-slot">
                        <?php if ($current_view !== 'log'): ?>
                        <!-- 정렬 필터 -->
                        <div class="filter-control flex items-center">
                            <i class="fas fa-sort-amount-down text-[10px] text-slate-400 dark:text-slate-500 mr-2"></i>
                            <select id="sortOrder" onchange="triggerSearch()" class="bg-transparent text-xs font-black outline-none cursor-pointer text-[var(--text-main)] dark:text-white pr-1">
                                <option value="latest" <?= $sort=='latest'?'selected':'' ?>><?= $t['sort_latest'] ?></option>
                                <option disabled>──────────</option>
                                <option value="comp_asc" <?= $sort=='comp_asc'?'selected':'' ?>><?= $t['sort_comp_asc'] ?></option>
                                <option value="comp_desc" <?= $sort=='comp_desc'?'selected':'' ?>><?= $t['sort_comp_desc'] ?></option>
                                <option disabled>──────────</option>
                                <option value="retry_desc" <?= $sort=='retry_desc'?'selected':'' ?>><?= $t['sort_retry_desc'] ?? '체크 횟수 내림차순' ?></option>
                                <option value="retry_asc" <?= $sort=='retry_asc'?'selected':'' ?>><?= $t['sort_retry_asc'] ?? '체크 횟수 오름차순' ?></option>
                                <option disabled>──────────</option>
                                <option value="status_desc" <?= $sort=='status_desc'?'selected':'' ?>><?= $t['sort_status_desc'] ?></option>
                                <option value="status_asc" <?= $sort=='sort_status_asc'?'selected':'' ?>><?= $t['sort_status_asc'] ?></option>
                                <option disabled>──────────</option>
                                <option value="result_succ" <?= $sort=='result_succ'?'selected':'' ?>><?= $t['sort_result_succ'] ?></option>
                                <option value="result_fail" <?= $sort=='result_fail'?'selected':'' ?>><?= $t['sort_result_fail'] ?></option>
                                <option disabled>──────────</option>
                                <option value="ssl" <?= $sort=='ssl'?'selected':'' ?>><?= $t['sort_ssl'] ?></option>
                                <option value="dom" <?= $sort=='dom'?'selected':'' ?>><?= $t['sort_dom'] ?></option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="filter-refresh-control flex items-center justify-end gap-2">
                        <?php if ($current_view === 'log'): ?>
                        <!-- 수동 새로고침 버튼 -->
                        <button onclick="refreshLogs(true)" class="flex items-center justify-center w-9 h-9 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/50 dark:border-slate-700/50 hover:border-[#18aee7] hover:text-[#18aee7] transition-all shadow-sm" title="<?= $current_lang == 'KO' ? '로그 새로고침' : 'Refresh Logs' ?>">
                            <i class="fas fa-sync-alt text-xs text-[var(--text-sub)] hover:text-[#18aee7]"></i>
                        </button>
                        <?php endif; ?>
                        <label class="live-refresh-toggle flex items-center cursor-pointer" title="<?= $current_view === 'log'
                                ? ($current_lang == 'KO' ? '1분 간격으로 로그를 자동 조회합니다.' : 'Refresh logs every minute.')
                                : ($current_lang == 'KO' ? '1분 간격으로 모니터링 결과를 자동 조회합니다.' : 'Refresh monitor results every minute.') ?>">
                                <input type="checkbox" id="autoRefreshToggle" class="sr-only peer" aria-label="<?= $current_lang == 'KO' ? '실시간 조회' : 'Live refresh' ?>" onchange="toggleAutoRefresh(this.checked)">
                                <span class="live-refresh-switch" aria-hidden="true">
                                    <span class="live-refresh-switch-knob"></span>
                                </span>
                                <span class="live-refresh-label text-xs font-bold text-[var(--text-main)] select-none"><?= $current_lang == 'KO' ? '실시간 조회' : 'Live refresh' ?></span>
                        </label>
                        <script>
                            document.getElementById('autoRefreshToggle').checked =
                                sessionStorage.getItem('webmon_auto_refresh') !== 'false';
                            document.documentElement.classList.remove('live-refresh-initializing');
                        </script>
                    </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="listHeaderWrapper" class="w-full px-8 bg-[var(--bg-color)] pb-2 <?= $current_view !== 'list' ? 'hidden' : '' ?>">
            <div class="max-w-7xl mx-auto">
                <div class="py-3 pr-5 pl-7 font-black text-[12px] text-[var(--text-sub)] bg-[var(--card-bg)] border border-[var(--border-color)] border-b-2 border-b-[#18aee7] rounded-xl flex items-center">
                    <div class="w-10"></div>
                    <div class="flex-1"><?= $t['h_group'] ?></div>
                    <div class="flex-[1.2] cursor-pointer hover:text-[#18aee7] transition-colors flex items-center gap-1" onclick="toggleSortCompany()">
                        <?= $t['h_company'] ?>
                        <?php if($sort == 'comp_asc'): ?><i class="fas fa-sort-alpha-down text-[10px]"></i>
                        <?php elseif($sort == 'comp_desc'): ?><i class="fas fa-sort-alpha-up text-[10px]"></i>
                        <?php else: ?><i class="fas fa-sort text-gray-300 opacity-50 text-[10px]"></i><?php endif; ?>
                    </div>
                    <div class="flex-[2.5] text-[#18aee7] pl-4"><?= $t['h_url'] ?></div>
                    <div class="flex-1 text-center"><?= $t['h_keyword'] ?></div>
                    <div class="flex-1 text-center cursor-pointer hover:text-[#18aee7] transition-colors" onclick="toggleSortStatus()"><?= $t['h_alarm'] ?></div>
                    <div class="flex-1 text-center cursor-pointer hover:text-[#18aee7] transition-colors" onclick="toggleSortResult()"><?= $t['h_result'] ?></div>
                    <div class="w-28 text-right pr-2"><?= $t['h_setting'] ?></div>
                </div>
            </div>
        </div>

        <!-- 로그 뷰 헤더 -->
        <div id="logHeaderWrapper" class="w-full px-8 bg-[var(--bg-color)] pb-2 <?= $current_view !== 'log' ? 'hidden' : '' ?>">
            <div class="max-w-7xl mx-auto">
                <div class="py-3 pr-5 pl-7 font-black text-[12px] text-[var(--text-sub)] bg-[var(--card-bg)] border border-[var(--border-color)] border-b-2 border-b-[#18aee7] rounded-xl flex items-center">
                    <div class="w-48"><?= $current_lang == 'KO' ? '시각' : 'Time' ?></div>
                    <div class="w-40"><?= $current_lang == 'KO' ? '그룹' : 'Group' ?></div>
                    <div class="w-48"><?= $current_lang == 'KO' ? '업체명' : 'Company' ?></div>
                    <div class="flex-1"><?= $current_lang == 'KO' ? 'URL / 실행 명령어' : 'URL / Command' ?></div>
                    <div class="w-28 text-center"><?= $current_lang == 'KO' ? '응답속도' : 'Response Time' ?></div>
                    <div class="w-28 text-center"><?= $current_lang == 'KO' ? '결과' : 'Result' ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="w-full px-8 pb-20 pt-2">
        <div class="max-w-7xl mx-auto">
            <?php if ($current_view == 'log'): ?>
                <!-- 실시간 로그뷰 -->
                <?php include __DIR__ . '/components/log_view.php'; ?>
            <?php else: ?>
                <form id="bulkForm" method="POST" action="website_status.php">
                    <input type="hidden" name="action" id="bulkAction" value="">
                    <input type="hidden" name="status" id="bulkStatusValue" value="">
                    <input type="hidden" name="ids" id="bulkIds" value="">
                    <input type="hidden" name="current_query" id="bulkCurrentQuery" value="">
                    
                    <div id="mainForm" class="<?= $current_view == 'card' ? 'monitor-card-grid card-columns-' . $current_card_columns : 'view-list' ?>">
                        <?php include __DIR__ . '/components/monitor_items.php'; ?>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    </div>

    <?php include __DIR__ . '/components/modal_settings.php'; ?>
    <?php include __DIR__ . '/components/modal_schedule.php'; ?>
    <?php include __DIR__ . '/components/confirm_dialog.php'; ?>

    <?php include __DIR__ . '/components/modal_form.php'; ?>
    <?php include __DIR__ . '/components/modal_csv_import.php'; ?>
    <?php include __DIR__ . '/components/modal_bulk.php'; ?>
    <?php include __DIR__ . '/components/modal_history.php'; ?>

    <script src="assets/js/script.js?v=<?=@filemtime(__DIR__ . '/../assets/js/script.js') ?: '2.1'?>"></script>

    <script src="assets/js/dashboard-charts.js?v=<?=@filemtime(__DIR__ . '/../assets/js/dashboard-charts.js') ?: '2.1'?>"></script>
    <script src="assets/js/dashboard-settings.js?v=<?=@filemtime(__DIR__ . '/../assets/js/dashboard-settings.js') ?: '2.1'?>"></script>
</body>
</html>
