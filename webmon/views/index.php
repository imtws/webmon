<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

// 만료일 기준 D-Day 및 색상 클래스 계산 헬퍼 함수
function getExpiryColorAndText($date, $monEnabled) {
    if (!$monEnabled || empty($date)) {
        return ['class' => 'text-slate-400', 'badge' => '', 'dday' => ''];
    }
    
    $today = new DateTime(date('Y-m-d'));
    $expiry = new DateTime($date);
    $interval = $today->diff($expiry);
    $days = (int)$interval->format('%r%a'); // 남은 일수
    
    if ($days < 0) {
        return [
            'class' => 'text-red-600 dark:text-red-400 font-extrabold',
            'badge' => '<span class="bg-red-500 text-white px-2 py-0.5 rounded-md text-[9px] font-black inline-block ml-1.5 animate-pulse shadow-sm shadow-red-500/10">만료됨</span>',
            'dday' => '만료됨'
        ];
    } elseif ($days <= 14) {
        return [
            'class' => 'text-red-600 dark:text-red-400 font-extrabold',
            'badge' => '<span class="bg-red-500 text-white px-2 py-0.5 rounded-md text-[9px] font-black inline-block ml-1.5 shadow-sm shadow-red-500/10">D-' . $days . '</span>',
            'dday' => "D-{$days}"
        ];
    } elseif ($days <= 30) {
        return [
            'class' => 'text-amber-600 dark:text-amber-500 font-extrabold',
            'badge' => '<span class="bg-amber-500 text-white px-2 py-0.5 rounded-md text-[9px] font-black inline-block ml-1.5 shadow-sm shadow-amber-500/10">D-' . $days . '</span>',
            'dday' => "D-{$days}"
        ];
    }
    
    return [
        'class' => 'text-slate-700 dark:text-slate-300 font-bold',
        'badge' => '<span class="bg-slate-100 dark:bg-slate-800/80 text-slate-600 dark:text-slate-200 px-2 py-0.5 rounded-md text-[9px] font-bold inline-block ml-1.5">D-' . $days . '</span>',
        'dday' => "D-{$days}"
    ];
}

// 로그 뷰일 때 DB 데이터 쿼리
if (($current_view ?? 'card') === 'log') {
    $log_max_lines = min(100, max(20, (int)($settings['log_max_lines'] ?? 100)));
    $log_before_id = max(0, (int)($_GET['before_log_id'] ?? 0));
    
    // c.로 컬럼이 명확하도록 WHERE문 재생성
    $log_where = ["1=1"];
    if ($type == 'success') {
        $log_where[] = "c.`check` = 'alarm' AND l.result > 0";
    } elseif ($type == 'failure') {
        $log_where[] = "l.result = 0 AND c.`check` != 'disable'";
    } elseif ($type == 'expiring') {
        $log_where[] = "c.`check` != 'disable' AND (
            (c.ssl_mon = 1 AND c.ssl_expiry IS NOT NULL AND c.ssl_expiry <= DATE_ADD(NOW(), INTERVAL 30 DAY))
            OR
            (c.dom_mon = 1 AND c.dom_expiry IS NOT NULL AND c.dom_expiry <= DATE_ADD(NOW(), INTERVAL 30 DAY))
        )";
    }

    if (!empty($search_company)) {
        $safe_comp = $db->real_escape_string($search_company);
        $regex = Utils::buildChosungRegex($search_company);
        if ($regex !== false) {
            $safe_regex = $db->real_escape_string($regex);
            $log_where[] = "(c.company LIKE '%$safe_comp%' OR c.url LIKE '%$safe_comp%' OR c.company REGEXP '$safe_regex')";
        } else {
            $log_where[] = "(c.company LIKE '%$safe_comp%' OR c.url LIKE '%$safe_comp%')";
        }
    }

    if ($search_group !== 'all') {
        $safe_grp = $db->real_escape_string($search_group);
        $log_where[] = "c.group_name = '$safe_grp'";
    }

    if ($filter_status !== 'all') {
        $safe_status = $db->real_escape_string($filter_status);
        if ($safe_status === 'cross_on') {
            $log_where[] = "c.cross_check = 1";
        } elseif ($safe_status === 'cross_off') {
            $log_where[] = "c.cross_check = 0";
        } elseif ($safe_status === 'timeout_custom') {
            $log_where[] = "c.timeout_sec > 2";
        } elseif ($safe_status === 'timeout_default') {
            $log_where[] = "c.timeout_sec <= 2";
        } else {
            $log_where[] = "c.`check` = '$safe_status'";
        }
    }

    if ($filter_engine === 'lynx') {
        $log_where[] = "(c.engine_type = 'lynx' OR c.engine_type IS NULL OR c.engine_type = '')";
    } elseif ($filter_engine === 'curl') {
        $log_where[] = "c.engine_type = 'curl'";
    }

    $log_where_sql = implode(' AND ', $log_where);
    $log_cursor_sql = $log_before_id > 0 ? " AND l.id < $log_before_id" : '';
    $log_query = "
        SELECT STRAIGHT_JOIN l.*, c.group_name, c.company, c.url, c.engine_type
        FROM monitor_log l FORCE INDEX (PRIMARY)
        INNER JOIN monitor_conf c ON l.monitor_conf_no = c.no
        WHERE $log_where_sql $log_cursor_sql
        ORDER BY l.id DESC
        LIMIT " . ($log_max_lines + 1);
    $log_result = $db->query($log_query);
}
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
    
    <link rel="stylesheet" href="assets/css/style.css?v=<?=time()?>">
    <script>
        tailwind.config = { darkMode: 'class' };
        const langPack = <?= isset($js_lang) ? $js_lang : '{}' ?>;
        const defaultSettings = {
            emailMode: '<?= htmlspecialchars($settings['def_email_mode'] ?? 'none') ?>',
            senderEmail: '<?= htmlspecialchars($settings['def_sender_email'] ?? '') ?>',
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

    <?php if ($db_pass === 'your_secure_password_here' || empty($db_pass)): ?>
    <div class="security-banner">
        <i class="fas fa-shield-alt" aria-hidden="true"></i>
        <span><?= $current_lang == 'KO' ? '기본 DB 비밀번호를 사용 중입니다.' : 'The default database password is in use.' ?></span>
        <button type="button" onclick="openSettingsModal(); switchSettingsTab('sys');"><?= $current_lang == 'KO' ? '변경하기' : 'Change password' ?></button>
    </div>
    <?php endif; ?>

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
                                ? ($current_lang == 'KO' ? '10초 간격으로 로그를 자동 조회합니다.' : 'Refresh logs every 10 seconds.')
                                : ($current_lang == 'KO' ? '30초 간격으로 모니터링 결과를 자동 조회합니다.' : 'Refresh monitor results every 30 seconds.') ?>">
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
                <div class="view-list flex flex-col gap-3">
                    <?php
                    $log_rows = [];
                    $log_has_more = isset($log_result) && $log_result->num_rows > $log_max_lines;
                    if (isset($log_result)) {
                        while ($log_row = $log_result->fetch_assoc()) {
                            if (count($log_rows) >= $log_max_lines) break;
                            $log_rows[] = $log_row;
                        }
                    }
                    $next_log_cursor = !empty($log_rows) ? (int)end($log_rows)['id'] : 0;
                    ?>
                    <?php if (!empty($log_rows)): ?>
                        <?php foreach($log_rows as $log_row):
                            $border_color = $log_row['result'] > 0 ? 'bg-[#18aee7]' : 'bg-red-500';
                            $result_badge_class = $log_row['result'] > 0 
                                ? 'bg-green-50 dark:bg-green-950/40 text-green-500 dark:text-green-400 border border-green-200/50 dark:border-green-800/50' 
                                : 'bg-red-50 dark:bg-rose-950/40 text-red-500 dark:text-rose-400 border border-red-200/50 dark:border-rose-800/50';
                            $result_text = $log_row['result'] > 0 ? $t['v_success'] : $t['v_fail'];
                            $result_icon = $log_row['result'] > 0 ? 'fa-check-circle' : 'fa-exclamation-circle';
                        ?>
                            <div class="log-entry relative bg-[var(--card-bg)] border border-[var(--border-color)] rounded-xl py-3 pr-5 pl-7 flex items-center hover:shadow-md transition-all text-[13px] hover:z-[110]">
                                <div class="absolute top-0 left-0 w-1.5 h-full <?= $border_color ?> rounded-l-xl"></div>
                                <!-- 시각 -->
                                <div class="w-48 text-[var(--text-sub)] dark:text-slate-300 font-mono">
                                    <?= htmlspecialchars($log_row['log_time']) ?>
                                </div>
                                <!-- 그룹 -->
                                <div class="w-40 font-semibold text-slate-500 dark:text-slate-300 truncate pr-4">
                                    <?= htmlspecialchars($log_row['group_name'] ?: '기본 그룹') ?>
                                </div>
                                <!-- 업체명 -->
                                <div class="w-48 font-black text-[var(--text-main)] dark:text-white truncate pr-4">
                                    <?= htmlspecialchars($log_row['company']) ?>
                                </div>
                                <!-- URL / 실행 명령어 -->
                                <div class="flex-1 pr-4 min-w-0">
                                    <div class="flex flex-col gap-1">
                                        <a href="<?= htmlspecialchars($log_row['req_url']) ?>" target="_blank" class="text-[#18aee7] hover:underline font-bold truncate max-w-full block">
                                            <?= htmlspecialchars($log_row['req_url']) ?>
                                        </a>
                                        <?php if (!empty($log_row['cmd'])): ?>
                                            <div class="flex items-center gap-1.5 mt-0.5">
                                                <div class="relative group flex-1 min-w-0">
                                                    <div class="bg-slate-100 dark:bg-slate-800/80 text-[10px] text-slate-600 dark:text-slate-200 font-mono rounded px-2 py-1 select-all truncate border border-slate-200 dark:border-slate-700/60 cursor-help" title="<?= htmlspecialchars($log_row['cmd']) ?>">
                                                        <i class="fas fa-terminal text-[8px] mr-1 text-slate-400"></i><?= Utils::highlightCommand($log_row['cmd']) ?>
                                                    </div>
                                                    <div class="absolute bottom-full left-0 z-[120] mb-2 w-max max-w-lg hidden group-hover:block bg-slate-900 text-white text-[10px] rounded p-2 shadow-lg font-mono break-all border border-slate-700 cmd-tooltip">
                                                        <?= Utils::highlightCommand($log_row['cmd']) ?>
                                                    </div>
                                                </div>
                                                <button type="button" onclick="copyCmd(this)" data-cmd="<?= htmlspecialchars($log_row['cmd'], ENT_QUOTES, 'UTF-8') ?>" class="flex items-center justify-center p-1.5 rounded bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-[#18aee7] hover:text-[#18aee7] text-slate-400 transition-all cursor-pointer flex-shrink-0" title="<?= $current_lang == 'KO' ? '명령어 복사' : 'Copy command' ?>">
                                                    <i class="far fa-copy text-[10px]"></i>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-[10px] text-gray-400 italic"><?= $current_lang == 'KO' ? '실행 명령 기록 없음' : 'No execution command logged' ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <!-- 응답속도 -->
                                <div class="w-28 text-center font-bold font-mono text-slate-700 dark:text-slate-200">
                                    <?= number_format($log_row['response_time'], 3) ?>s
                                </div>
                                <!-- 결과 -->
                                <div class="w-28 text-center">
                                    <span class="inline-flex items-center justify-center px-2 py-1 text-[10px] font-black rounded-lg <?= $result_badge_class ?>">
                                        <i class="fas <?= $result_icon ?> mr-1 text-[9px]"></i><?= $result_text ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($log_has_more): ?>
                            <button type="button" class="load-more-logs" data-cursor="<?= $next_log_cursor ?>">
                                <?= $current_lang == 'KO' ? '이전 로그 더 보기' : 'Load older logs' ?>
                            </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="bg-[var(--card-bg)] border border-[var(--border-color)] rounded-xl py-10 text-center text-[var(--text-sub)]">
                            <?= $current_lang == 'KO' ? '조회된 로그가 없습니다.' : 'No logs found.' ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <form id="bulkForm" method="POST" action="website_status.php">
                    <input type="hidden" name="action" id="bulkAction" value="">
                    <input type="hidden" name="status" id="bulkStatusValue" value="">
                    <input type="hidden" name="ids" id="bulkIds" value="">
                    <input type="hidden" name="current_query" id="bulkCurrentQuery" value="">
                    
                    <div id="mainForm" class="<?= $current_view == 'card' ? 'monitor-card-grid card-columns-' . $current_card_columns : 'view-list' ?>">
                        <?php 
                        if($result && $result->num_rows > 0) {
                            while($row = $result->fetch_assoc()): 
                                $is_disabled = ($row['check'] == 'disable');
                                
                                // 스케줄 상태 판별 (일시 무음 시간 판별)
                                $is_scheduled = (($row['sched_enabled'] ?? 0) == 1);
                                $is_currently_off_schedule = false;
                                if ($is_scheduled && !$is_disabled && !empty($row['mute_until'])) {
                                    $now = new DateTime();
                                    $mute_until = new DateTime($row['mute_until']);
                                    if ($now < $mute_until) {
                                        $is_currently_off_schedule = true;
                                    }
                                }

                                if ($is_disabled) {
                                    $border_color = 'bg-gray-400';
                                    $status_class = 'text-gray-400';
                                    $check_text = $t['status_disable'];
                                } elseif ($is_currently_off_schedule) {
                                    $border_color = 'bg-violet-400';
                                    $status_class = 'text-violet-500';
                                    $check_text = $current_lang == 'KO' ? '스케줄 무음' : 'Sched Mute';
                                } else {
                                    $border_color = $row['value'] > 0 ? 'bg-[#18aee7]' : 'bg-red-500';
                                    $status_class = ($row['check'] == 'alarm') ? "text-green-500" : "text-orange-500";
                                    $check_text = ($row['check'] == 'alarm') ? $t['status_alarm'] : $t['status_noalarm'];
                                }

                                $result_text = $row['value'] > 0 ? $t['v_success'] : $t['v_fail'];
                                $result_color = $row['value'] > 0 ? 'text-green-500' : 'text-red-500';
                                if ($is_currently_off_schedule) {
                                    $result_text = $current_lang == 'KO' ? '대기중' : 'Standby';
                                    $result_color = 'text-violet-400';
                                }
                                $group_name_display = htmlspecialchars($row['group_name'] ?: '기본 그룹');
                        ?>
                        
                        <div data-monitor-id="<?= (int)$row['no'] ?>" class="monitor-item group relative bg-[var(--card-bg)] overflow-visible transition-all border border-[var(--border-color)] rounded-xl <?= $is_disabled ? 'opacity-60' : '' ?> hover:shadow-md">
                            <div class="monitor-status-edge absolute top-0 left-0 w-1.5 h-full <?= $border_color ?>"></div>

                            <?php if (($current_view ?? 'card') === 'card'): ?>
                                <?php include 'views/components/item_card.php'; ?>
                            <?php elseif ($current_view === 'list'): ?>
                                <?php include 'views/components/item_list.php'; ?>
                            <?php endif; ?>
                        </div>
                        <?php 
                            endwhile; 
                        } else {
                            echo "<div class='col-span-full text-center py-10 text-[var(--text-sub)]'>데이터가 없습니다.</div>";
                        }
                        ?>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    </div>

    <!-- ⚙️ 환경 설정 모달 ────────────────────────────────────────────── -->
    <div id="settingsModal" class="side-sheet-overlay fixed inset-0 z-[1000] hidden justify-end bg-black/40 backdrop-blur-sm">
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
                                        <span class="font-mono text-xs text-orange-500">
                                            {company} (<?= $current_lang == 'KO' ? '회사명' : 'Company' ?>), 
                                            {url} (<?= $current_lang == 'KO' ? '대상 URL' : 'URL' ?>), 
                                            {keyword} (<?= $current_lang == 'KO' ? '검색 키워드' : 'Keyword' ?>), 
                                            {time} (<?= $current_lang == 'KO' ? '발생 시각' : 'Time' ?>), 
                                            {YYYY-MM-DD} (<?= $current_lang == 'KO' ? '발생 날짜' : 'Date' ?>),
                                            {engine} (<?= $current_lang == 'KO' ? '실행 엔진' : 'Engine' ?>), 
                                            {response_time} (<?= $current_lang == 'KO' ? '응답속도' : 'Response Time' ?>), 
                                            {comment} (<?= $current_lang == 'KO' ? '가이드 권장 처리 내용' : 'Action Guide' ?>), 
                                            {web_url} (<?= $current_lang == 'KO' ? 'WebMon 주소' : 'Dashboard URL' ?>)
                                        </span>
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '이메일 HTML 템플릿' : 'Email HTML Template' ?></label>
                                    <textarea name="email_template_html" rows="8" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs font-mono outline-none focus:border-[#18aee7]" placeholder="<?= $current_lang == 'KO' ? 'HTML 코드를 입력해 주세요...' : 'Enter HTML template...' ?>"><?= htmlspecialchars($settings['email_template_html'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <!-- Plain Text 템플릿 작성 영역 (Plain 모드 시 노출) -->
                            <div id="plainTemplateFields" class="hidden space-y-4">
                                <div class="p-3 bg-blue-50/50 dark:bg-slate-800/40 border border-blue-200 dark:border-slate-700 rounded-lg text-xs">
                                    <h5 class="font-bold text-[#18aee7] mb-1"><i class="fas fa-info-circle"></i> <?= $current_lang == 'KO' ? '사용 가능한 변수 안내 (Plain Text)' : 'Available Variables (Plain Text)' ?></h5>
                                    <p class="text-[11px] text-[var(--text-sub)] leading-relaxed">
                                        <?= $current_lang == 'KO' ? '아래 일반 텍스트 템플릿 내에 변수를 삽입하면 알림 발송 시 실제 값으로 자동 치환됩니다:' : 'Insert variables into the plain text templates below to replace with actual values:' ?><br>
                                        <span class="font-mono text-xs text-orange-500">
                                            {company} (<?= $current_lang == 'KO' ? '회사명' : 'Company' ?>), 
                                            {url} (<?= $current_lang == 'KO' ? '대상 URL' : 'URL' ?>), 
                                            {keyword} (<?= $current_lang == 'KO' ? '검색 키워드' : 'Keyword' ?>), 
                                            {time} (<?= $current_lang == 'KO' ? '발생 시각' : 'Time' ?>), 
                                            {YYYY-MM-DD} (<?= $current_lang == 'KO' ? '발생 날짜' : 'Date' ?>),
                                            {engine} (<?= $current_lang == 'KO' ? '실행 엔진' : 'Engine' ?>), 
                                            {response_time} (<?= $current_lang == 'KO' ? '응답속도' : 'Response Time' ?>), 
                                            {comment} (<?= $current_lang == 'KO' ? '가이드 권장 처리 내용' : 'Action Guide' ?>), 
                                            {web_url} (<?= $current_lang == 'KO' ? 'WebMon 주소' : 'Dashboard URL' ?>)
                                        </span>
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '이메일 Plain Text 템플릿' : 'Email Plain Text Template' ?></label>
                                    <textarea name="email_template_plain" rows="8" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs font-mono outline-none focus:border-[#18aee7]" placeholder="<?= $current_lang == 'KO' ? '일반 텍스트 형식의 템플릿을 입력해 주세요...' : 'Enter template in plain text format...' ?>"><?= htmlspecialchars($settings['email_template_plain'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <!-- Telegram messages are sent as plain text. -->
                            <div class="mt-4">
                                <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '텔레그램 일반 텍스트 템플릿' : 'Telegram Plain Text Template' ?></label>
                                <p class="mb-2 text-[10px] text-[var(--text-sub)]"><?= $current_lang == 'KO' ? '같은 점검 주기의 장애를 한 메시지로 묶어 보냅니다. {detections}와 {recommendations}에 항목별 내용이 들어가며, {time}, {YYYY-MM-DD}, {web_url}은 공통 정보입니다. 기존 단일 항목 변수도 사용할 수 있습니다.' : 'Failures from the same check cycle are combined into one message. {detections} and {recommendations} contain per-item details; {time}, {YYYY-MM-DD} and {web_url} are shared. Legacy single-item variables are also supported.' ?></p>
                                <textarea name="tg_template_plain" rows="6" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs font-mono outline-none focus:border-[#18aee7]" placeholder="<?= $current_lang == 'KO' ? '일반 텍스트 템플릿을 입력해 주세요...' : 'Enter a plain text template...' ?>"><?= htmlspecialchars($settings['tg_template_plain'] ?? '') ?></textarea>
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

                    <!-- 4. DB 비밀번호 변경 -->
                    <div class="settings-accordion">
                        <button type="button" onclick="toggleSettingsAccordion('dbpass')" class="w-full flex justify-between items-center p-4 font-bold text-left focus:outline-none select-none hover:bg-slate-100/50 dark:hover:bg-slate-800/50 transition-colors">
                            <span class="flex items-center gap-2 text-red-500"><i class="fas fa-key"></i> <?= $current_lang == 'KO' ? 'DB 비밀번호 변경' : 'Change DB Password' ?></span>
                            <i id="set-arrow-dbpass" class="fas fa-chevron-down text-xs text-[var(--text-sub)] transition-transform duration-200"></i>
                        </button>
                        <div id="set-body-dbpass" class="p-4 border-t border-[var(--border-color)] hidden">
                            <?php $usesDefaultDbPassword = isset($db_pass) && ($db_pass === '' || hash_equals('your_secure_password_here', $db_pass)); ?>
                            <p class="text-[11px] text-[var(--text-sub)] mb-3"><?= $usesDefaultDbPassword
                                ? ($current_lang == 'KO' ? '초기 DB 비밀번호를 사용 중입니다. 이전 비밀번호 입력 없이 새 비밀번호만 입력하면 변경됩니다.' : 'The initial database password is in use. Enter only a new password; no current password is needed.')
                                : ($current_lang == 'KO' ? 'DB 비밀번호를 변경하려면 이전 비밀번호와 새 비밀번호를 입력하세요.' : 'Enter the current and new passwords to change the database password.') ?></p>
                            <div class="grid grid-cols-1 <?= $usesDefaultDbPassword ? '' : 'md:grid-cols-2' ?> gap-4">
                                <?php if (!$usesDefaultDbPassword): ?>
                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '이전 DB 패스워드' : 'Current DB Password' ?></label>
                                    <input type="password" name="db_pass_old" autocomplete="current-password" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]" placeholder="<?= $current_lang == 'KO' ? '이전 패스워드 입력' : 'Enter current password' ?>">
                                </div>
                                <?php endif; ?>
                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '변경할 패스워드' : 'New DB Password' ?></label>
                                    <input type="password" name="db_pass_new" autocomplete="new-password" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]" placeholder="<?= $current_lang == 'KO' ? '변경할 패스워드 입력' : 'Enter new password' ?>">
                                </div>
                            </div>
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
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '로그 보존 기간 (일)' : 'Log Retention (Days)' ?></label>
                                    <input type="number" name="retention_days" min="1" max="365" value="<?= htmlspecialchars($settings['retention_days'] ?? '30') ?>" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '웹 대시보드 URL (알림 링크용)' : 'Dashboard URL' ?></label>
                                    <input type="text" name="webmon_url" value="<?= htmlspecialchars($settings['webmon_url'] ?? '') ?>" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]" placeholder="http://your-server-ip:8080">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '로그 페이지당 조회 건수 (20–100)' : 'Rows per log page (20–100)' ?></label>
                                    <input type="number" name="log_max_lines" min="20" max="100" value="<?= min(100, max(20, (int)($settings['log_max_lines'] ?? 100))) ?>" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '엔진 로그 파일당 크기 (MB)' : 'Engine log file size (MB)' ?></label>
                                    <p class="mb-1 text-[10px] text-[var(--text-sub)]"><?= $current_lang == 'KO' ? '최대 4개 백업 파일을 보관합니다.' : 'Keeps up to 4 rotated backups.' ?></p>
                                    <input type="number" name="log_rotate_mb" min="1" max="1000" value="<?= htmlspecialchars($settings['log_rotate_mb'] ?? '50') ?>" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]">
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

    <!-- 🔔 개별 모니터링 스케줄 설정 모달 ────────────────────────────── -->
    <!-- 🔔 개별 모니터링 알람 일시 무음 설정 모달 ────────────────────────────── -->
    <div id="itemScheduleModal" class="side-sheet-overlay fixed inset-0 z-[1000] hidden justify-end bg-black/40 backdrop-blur-sm">
        <div class="side-sheet-panel bg-[var(--card-bg)] border-l border-[var(--border-color)] w-full max-w-md h-full shadow-2xl flex flex-col overflow-y-auto" role="dialog" aria-modal="true">
            <div class="sheet-header">
                <h3 class="sheet-header-title"><i class="fas fa-bell-slash"></i><span id="schedModalTitle">알람 일시 무음 설정</span></h3>
                <button type="button" class="sheet-close-button" aria-label="<?= $current_lang == 'KO' ? '닫기' : 'Close' ?>" onclick="closeItemScheduleModal()"><i class="fas fa-times"></i></button>
            </div>
            
            <form action="save_item_schedule.php" method="POST" class="sheet-form flex flex-col gap-4 p-5 text-sm">
                <input type="hidden" name="no" id="schedItemNo" value="">
                
                <!-- 무음 활성화 토글 -->
                <div class="flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/20 p-3 rounded-xl border border-[var(--border-color)]">
                    <div>
                        <span class="font-bold block"><?= $current_lang == 'KO' ? '일시 무음 설정 사용' : 'Enable Temporary Mute' ?></span>
                        <span class="text-[10px] text-[var(--text-sub)]"><?= $current_lang == 'KO' ? '지정한 일시까지 알람을 일시적으로 중지합니다.' : 'Temporarily disables notifications until the specified date and time.' ?></span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="sched_enabled" id="schedEnabled" value="1" class="sr-only peer" onchange="toggleSchedFields(this.checked)">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-[#18aee7]"></div>
                    </label>
                </div>

                <div id="schedFields" class="flex flex-col gap-4 transition-all duration-300">
                    <!-- 무음 종료 일시 설정 -->
                    <div>
                        <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '무음 종료 일시' : 'Mute Until (Date & Time)' ?></label>
                        <input type="datetime-local" name="mute_until" id="schedMuteUntil" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]" required>
                    </div>
                </div>

                <div class="sheet-footer -mx-5 -mb-5 mt-4">
                    <button type="submit" class="px-6 py-2.5 bg-[#18aee7] hover:bg-[#159bd0] text-white rounded-xl font-bold shadow-md transition-all text-xs"><?= $current_lang == 'KO' ? '스케줄 저장' : 'Save Schedule' ?></button>
                </div>
            </form>
        </div>
    </div>

    <div id="confirmDialog" class="fixed inset-0 z-[1300] hidden items-center justify-center bg-black/45 backdrop-blur-sm p-4" role="presentation">
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

    <?php include 'views/components/modal_form.php'; ?>
    <?php include 'views/components/modal_csv_import.php'; ?>
    <?php include 'views/components/modal_bulk.php'; ?>
    <?php include 'views/components/modal_history.php'; ?>

    <script src="assets/js/script.js?v=<?=time()?>"></script>

    <!-- 📊 아코디언 및 시계열 차트 로직 추가 -->
    <script>
        const charts = {};

        function toggleAccordion(event, no) {
            if (event) event.stopPropagation();
            const accordion = document.getElementById('accordion-' + no);
            const arrow = document.getElementById('arrow-' + no);
            const toggle = document.getElementById('chart-toggle-' + no);
            if (!accordion) return;

            if (accordion.classList.contains('hidden')) {
                accordion.classList.remove('hidden');
                accordion.classList.add('flex');
                arrow.style.transform = 'rotate(180deg)';
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'true');
                    toggle.setAttribute('aria-label', '<?= $current_lang == "KO" ? "응답 시간 그래프 닫기" : "Hide response-time chart" ?>');
                }
                loadChart(no, '1d');
            } else {
                accordion.classList.remove('flex');
                accordion.classList.add('hidden');
                arrow.style.transform = 'rotate(0deg)';
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.setAttribute('aria-label', '<?= $current_lang == "KO" ? "응답 시간 그래프 열기" : "Show response-time chart" ?>');
                }
            }
        }

        async function loadChart(no, range, start = '', end = '') {
            const canvas = document.getElementById('chart-canvas-' + no);
            const avgSpan = document.getElementById('avg-time-' + no);
            const rateSpan = document.getElementById('success-rate-' + no);
            if (!canvas) return;

            try {
                let url = `get_chart_data.php?no=${no}`;
                if (start && end) {
                    url += `&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`;
                } else {
                    url += `&range=${range}`;
                }
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.error) {
                    console.error(data.error);
                    return;
                }

                // 데이터 파싱
                const labels = data.map(item => {
                    const dt = new Date(item.time);
                    if (range === '1d') {
                        return dt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    } else if (!range) {
                        return (dt.getMonth() + 1) + '/' + dt.getDate() + ' ' + dt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
                    } else {
                        return (dt.getMonth() + 1) + '/' + dt.getDate() + ' ' + dt.getHours() + 'h';
                    }
                });
                const responseTimes = data.map(item => item.response_time);
                const results = data.map(item => item.result);
                const formatChartTimestamp = timestamp => {
                    const parsed = new Date(timestamp.replace(' ', 'T'));
                    return Number.isNaN(parsed.getTime()) ? timestamp : parsed.toLocaleString();
                };
                const isKo = document.documentElement.lang === 'ko';

                // 통계 연산
                if (responseTimes.length > 0) {
                    const sum = responseTimes.reduce((a, b) => a + b, 0);
                    const avg = sum / responseTimes.length;
                    avgSpan.innerText = avg.toFixed(3);

                    const successes = results.filter(r => r > 0).length;
                    const rate = (successes / results.length) * 100;
                    rateSpan.innerText = rate.toFixed(1);
                } else {
                    avgSpan.innerText = '-';
                    rateSpan.innerText = '-';
                }

                // 이전 차트 해제
                if (charts[no]) {
                    charts[no].destroy();
                }

                // 차트 그리기
                const ctx = canvas.getContext('2d');
                const isDark = document.documentElement.classList.contains('dark');
                const textColor = isDark ? '#cbd5e1' : '#64748b';
                const gridColor = isDark ? '#334155' : '#e2e8f0';

                const showTime = document.getElementById('show-time-' + no) ? document.getElementById('show-time-' + no).checked : true;
                const showRate = document.getElementById('show-rate-' + no) ? document.getElementById('show-rate-' + no).checked : true;

                // 장애(실패) 시점 하이라이팅 스타일 계산
                const labelsLength = labels.length;
                let ptColors, ptBorderColors, ptRadii, ptHoverRadii;

                if (showRate) {
                    ptColors = results.map(r => r > 0 ? '#18aee7' : '#ef4444');
                    ptBorderColors = results.map(r => r > 0 ? '#18aee7' : '#ef4444');
                    ptRadii = results.map(r => r > 0 ? (labelsLength > 50 ? 0 : 1.5) : 5);
                    ptHoverRadii = results.map(r => r > 0 ? 4 : 7);
                } else {
                    ptColors = results.map(() => '#18aee7');
                    ptBorderColors = results.map(() => '#18aee7');
                    ptRadii = results.map(() => labelsLength > 50 ? 0 : 1.5);
                    ptHoverRadii = results.map(() => 4);
                }

                charts[no] = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        resultsRaw: results,
                        responseTimesRaw: responseTimes,
                        datasets: [
                            {
                                label: 'Response Time (s)',
                                data: responseTimes,
                                borderColor: '#18aee7',
                                backgroundColor: 'rgba(24, 174, 231, 0.08)',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.3,
                                pointBackgroundColor: ptColors,
                                pointBorderColor: ptBorderColors,
                                pointRadius: ptRadii,
                                pointHoverRadius: ptHoverRadii,
                                yAxisID: 'y',
                                hidden: !showTime
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    title: function(items) {
                                        if (!items.length) return '';
                                        const point = data[items[0].dataIndex];
                                        const startTime = formatChartTimestamp(point.period_start || point.time);
                                        const endTime = formatChartTimestamp(point.period_end || point.time);
                                        const sampleCount = point.sample_count || 1;
                                        if (sampleCount === 1) return startTime;
                                        return isKo
                                            ? `${startTime} ~ ${endTime} (${sampleCount}회 평균)`
                                            : `${startTime} – ${endTime} (avg. of ${sampleCount} checks)`;
                                    },
                                    label: function(context) {
                                        const point = data[context.dataIndex];
                                        const responseLabel = isKo ? '평균 응답시간' : 'Avg response time';
                                        const resultLabel = isKo ? '결과' : 'Result';
                                        const status = point.result > 0
                                            ? (isKo ? '정상' : 'Success')
                                            : (isKo ? '장애 포함' : 'Failure detected');
                                        const lines = [
                                            `${responseLabel}: ${Number(point.response_time).toFixed(3)}s`,
                                            `${resultLabel}: ${status}`
                                        ];
                                        if ((point.sample_count || 1) > 1) {
                                            lines.push(isKo
                                                ? `장애 ${point.failure_count}회 / 전체 ${point.sample_count}회`
                                                : `${point.failure_count} failures / ${point.sample_count} checks`);
                                        }
                                        return lines;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: {
                                    maxTicksLimit: 8,
                                    color: textColor,
                                    font: { size: 9 }
                                }
                            },
                            y: {
                                type: 'linear',
                                display: showTime,
                                position: 'left',
                                beginAtZero: true,
                                grid: { color: gridColor },
                                ticks: {
                                    color: textColor,
                                    font: { size: 9 }
                                },
                                title: {
                                    display: true,
                                    text: 'Response Time (s)',
                                    color: textColor,
                                    font: { size: 9, weight: 'bold' }
                                }
                            }
                        }
                    }
                });

            } catch (err) {
                console.error('Failed to load chart data:', err);
            }
        }

        function updateChartVisibility(no) {
            const chart = charts[no];
            if (!chart) return;
            
            const showTime = document.getElementById('show-time-' + no) ? document.getElementById('show-time-' + no).checked : true;
            const showRate = document.getElementById('show-rate-' + no) ? document.getElementById('show-rate-' + no).checked : true;
            
            chart.setDatasetVisibility(0, showTime);
            chart.options.scales.y.display = showTime;
            
            const dataset = chart.data.datasets[0];
            const results = chart.data.resultsRaw;
            const labelsLength = chart.data.labels.length;
            
            if (showRate) {
                dataset.pointBackgroundColor = results.map(r => r > 0 ? '#18aee7' : '#ef4444');
                dataset.pointBorderColor = results.map(r => r > 0 ? '#18aee7' : '#ef4444');
                dataset.pointRadius = results.map(r => r > 0 ? (labelsLength > 50 ? 0 : 1.5) : 5);
                dataset.pointHoverRadius = results.map(r => r > 0 ? 4 : 7);
            } else {
                dataset.pointBackgroundColor = results.map(() => '#18aee7');
                dataset.pointBorderColor = results.map(() => '#18aee7');
                dataset.pointRadius = results.map(() => labelsLength > 50 ? 0 : 1.5);
                dataset.pointHoverRadius = results.map(() => 4);
            }
            
            chart.update();
        }

        async function loadCustomChart(no) {
            const startVal = document.getElementById('chart-start-' + no).value;
            const endVal = document.getElementById('chart-end-' + no).value;
            if (!startVal || !endVal) {
                showToast('<?= $current_lang == "KO" ? "시작일과 종료일을 모두 선택해주세요." : "Please select both start and end dates." ?>', 'warning');
                return;
            }
            
            // 날짜 논리 검증 (시작일이 종료일보다 늦을 경우) - 브라우저 크로스 호환성을 위해 대시를 슬래시로 변경
            const startDate = new Date(startVal.replace(/-/g, '/'));
            const endDate = new Date(endVal.replace(/-/g, '/'));
            
            if (startDate > endDate) {
                showToast('<?= $current_lang == "KO" ? "시작일은 종료일보다 이전 날짜여야 합니다." : "The start date must be before the end date." ?>', 'warning');
                return;
            }
            
            // 미래 날짜 제한 검증
            const now = new Date();
            if (startDate > now || endDate > now) {
                showToast('<?= $current_lang == "KO" ? "현재 시간 이후는 조회할 수 없습니다." : "You cannot query future times." ?>', 'warning');
                return;
            }
            
            loadChart(no, '', startVal, endVal);
        }

        function switchSettingsTab(tab) {
            const btnSys = document.getElementById('tabBtn-sys');
            const btnDef = document.getElementById('tabBtn-def');
            const contentSys = document.getElementById('tabContent-sys');
            const contentDef = document.getElementById('tabContent-def');
            
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
            // Open first accordions by default
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
                let msg = '<?= $current_lang == "KO" ? "설정 저장 중 오류가 발생했습니다." : "An error occurred while saving settings." ?>';
                if (err === 'db_pass_incorrect') {
                    msg = '<?= $current_lang == "KO" ? "이전 DB 패스워드가 올바르지 않습니다." : "The current DB password is incorrect." ?>';
                } else if (err === 'db_alter_failed') {
                    msg = '<?= $current_lang == "KO" ? "DB 패스워드 변경에 실패했습니다." : "Failed to change DB password." ?>';
                } else if (err === 'db_pass_required') {
                    msg = '<?= $current_lang == "KO" ? "새 비밀번호를 입력해 주세요. 기본 비밀번호 사용 중에는 이전 비밀번호를 비워둘 수 있습니다." : "Enter a new password. The current password may be left blank when the default is in use." ?>';
                } else if (err === 'sender_email_invalid') {
                    msg = '<?= $current_lang == "KO" ? "기본 발송 이메일 주소 형식을 확인해 주세요." : "Enter a valid default sender email address." ?>';
                } else if (err === 'db_pass_storage_failed') {
                    msg = '<?= $current_lang == "KO" ? "새 비밀번호를 안전하게 저장하지 못해 변경을 취소했습니다. 관리자에게 문의해 주세요." : "The password change was cancelled because it could not be stored securely." ?>';
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

            // 📅 응답속도 추이 그래프의 시작/종료일 Flatpickr 연동
            const fpLocale = document.documentElement.lang === 'ko' ? 'ko' : 'en';
            document.querySelectorAll('input[id^="chart-start-"], input[id^="chart-end-"]').forEach(el => {
                const isStart = el.id.includes('start');
                const defaultDate = isStart 
                    ? new Date(Date.now() - 24 * 60 * 60 * 1000) // 24시간 전
                    : new Date(); // 현재 시각

                flatpickr(el, {
                    enableTime: true,
                    enableSeconds: true,
                    dateFormat: "Y-m-d H:i:S",
                    time_24hr: true,
                    defaultDate: defaultDate,
                    locale: fpLocale,
                    allowInput: true
                });
            });

            // 🔍 URL 파라미터에 expand가 있으면 해당 아코디언을 자동으로 엽니다.
            if (urlParams.has('expand')) {
                const expandNo = urlParams.get('expand');
                setTimeout(() => {
                    toggleAccordion(null, expandNo);
                }, 500); // UI 안정화를 위한 지연
            }

            // ⚙️ URL 파라미터에 settings가 있으면 설정 모달을 자동으로 엽니다.
            if (urlParams.has('settings')) {
                setTimeout(() => {
                    openSettingsModal();
                }, 500); // UI 안정화를 위한 지연
            }
        });

        function openScheduleModal(event, row) {
            if (event) event.stopPropagation();
            document.getElementById('schedItemNo').value = row.no;
            document.getElementById('schedModalTitle').innerText = `${row.company} 알람 일시 무음 설정`;
            
            const enabled = parseInt(row.sched_enabled || 0) === 1;
            document.getElementById('schedEnabled').checked = enabled;
            
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
                const muteHours = parseInt(defaultSettings.muteHours || 2);
                muteTime = new Date(now.getTime() + (muteHours * 60 * 60 * 1000));
            }
            
            const localISOTime = new Date(muteTime.getTime() - offsetMs).toISOString().substring(0, 16);
            document.getElementById('schedMuteUntil').value = localISOTime;
            
            toggleSchedFields(enabled);
            
            openSideSheet('itemScheduleModal');
        }

        function closeItemScheduleModal() {
            closeSideSheet('itemScheduleModal');
        }

        function toggleSchedFields(enabled) {
            const fields = document.getElementById('schedFields');
            if (enabled) {
                fields.style.opacity = '1';
                fields.style.pointerEvents = 'auto';
            } else {
                fields.style.opacity = '0.5';
                fields.style.pointerEvents = 'none';
            }
        }
    </script>
</body>
</html>