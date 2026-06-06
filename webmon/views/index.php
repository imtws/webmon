<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
?>
<!DOCTYPE html>
<html lang="<?= strtolower($current_lang ?? 'ko') ?>" class="<?= ($current_theme ?? 'light') === 'dark' ? 'dark' : '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $t['logo'] ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <link rel="stylesheet" href="../assets/css/style.css?v=<?=time()?>">
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
            timeoutSec: '<?= htmlspecialchars($settings['def_timeout_sec'] ?? '2') ?>'
        };
    </script>
</head>
<body class="m-0 p-0 text-[var(--text-main)] transition-colors duration-200">

    <?php if ($db_pass === 'your_secure_password_here' || empty($db_pass)): ?>
    <div class="w-full bg-gradient-to-r from-red-600 to-orange-500 text-white px-8 py-3 text-center text-sm font-extrabold flex justify-center items-center gap-2 shadow-md">
        <i class="fas fa-shield-alt animate-bounce"></i>
        <span><?= $current_lang == 'KO' ? '보안 경고: 현재 기본/비어있는 DB 비밀번호가 사용 중입니다! 우측 상단 설정 아이콘(⚙️)을 눌러 안전한 DB 비밀번호로 변경(설정)해 주세요.' : 'Security Warning: Default or empty DB password is in use! Please click the settings icon (⚙️) on the top right to change/set a secure DB password.' ?></span>
    </div>
    <?php endif; ?>

    <div id="headerSticky" class="sticky top-0 z-[100] w-full bg-[var(--bg-color)] shadow-sm border-b border-[var(--border-color)]">
        <div class="px-8 pt-8 pb-3">
            <div class="max-w-7xl mx-auto flex flex-col gap-6">
                <div class="flex justify-between items-center">
                    <h1 onclick="location.href='website_status.php'" class="text-2xl text-[#18aee7] font-black cursor-pointer"><?= $t['logo'] ?></h1>
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <div onclick="toggleBulkDropdown(event)" class="unified-ui cursor-pointer px-4 bg-blue-50 dark:bg-slate-800 text-[#18aee7] border border-blue-200 dark:border-slate-600 hover:bg-blue-100 transition-colors">
                                <?= $t['btn_status'] ?> <i class="fas fa-chevron-down ml-2 text-[10px]"></i>
                            </div>
                            <div id="bulkStatusDropdown" class="custom-dropdown-card hidden absolute top-full right-0 mt-2 w-48 bg-[var(--card-bg)] shadow-xl rounded-xl z-50 border border-[var(--border-color)] overflow-hidden">
                                <div onclick="confirmBulkStatusUpdate('alarm')" class="p-3 hover:bg-blue-50 dark:hover:bg-slate-700 cursor-pointer text-sm font-bold text-green-500 border-b border-[var(--border-color)]"><?= $t['status_alarm'] ?></div>
                                <div onclick="confirmBulkStatusUpdate('no alarm')" class="p-3 hover:bg-blue-50 dark:hover:bg-slate-700 cursor-pointer text-sm font-bold text-orange-500 border-b border-[var(--border-color)]"><?= $t['status_noalarm'] ?></div>
                                <div onclick="confirmBulkStatusUpdate('disable')" class="p-3 hover:bg-blue-50 dark:hover:bg-slate-700 cursor-pointer text-sm font-bold text-gray-500"><?= $t['status_disable'] ?></div>
                            </div>
                        </div>
                        <button onclick="confirmBulkDeleteSubmit()" class="unified-ui px-4 bg-red-50 dark:bg-rose-900/20 text-red-500 border border-red-200 dark:border-rose-800 hover:bg-red-100"><?= $t['btn_del'] ?></button>
                        <button onclick="openModal('add')" class="unified-ui px-6 bg-[#18aee7] text-white hover:bg-[#159bd0] shadow-md"><?= $t['btn_add'] ?></button>
                        
                        <div class="flex items-center gap-2 ml-2">
                            <div class="switch-container">
                                <div onclick="toggleViewMode('card')" class="switch-btn <?= $current_view == 'card' ? 'active' : '' ?>"><i class="fas fa-th-large"></i></div>
                                <div onclick="toggleViewMode('list')" class="switch-btn <?= $current_view == 'list' ? 'active' : '' ?>"><i class="fas fa-list"></i></div>
                            </div>
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

                <div class="flex justify-between items-end px-1">
                    <div class="flex gap-4">
                        <div onclick="applyType('total')" class="stat-box px-5 py-3 bg-[var(--card-bg)] rounded-xl border border-[var(--border-color)] flex items-center gap-3 cursor-pointer <?= $type=='total'?'border-[#18aee7] bg-blue-50/30':'' ?>">
                            <i class="fas fa-th-list text-[var(--text-sub)] text-xl"></i>
                            <div><span class="text-[10px] font-bold text-[var(--text-sub)] block">TOTAL</span><span class="text-xl font-black"><?= $stat_total ?></span></div>
                        </div>
                        <div onclick="applyType('success')" class="stat-box px-5 py-3 bg-[var(--card-bg)] rounded-xl border border-[var(--border-color)] flex items-center gap-3 cursor-pointer <?= $type=='success'?'border-green-500 bg-green-50/50':'' ?>">
                            <i class="fas fa-check-circle text-green-500 text-xl"></i>
                            <div><span class="text-[10px] font-bold text-green-500 block">SUCCESS</span><span class="text-xl font-black text-green-500"><?= $stat_success ?></span></div>
                        </div>
                        <div onclick="applyType('failure')" class="stat-box px-5 py-3 bg-[var(--card-bg)] rounded-xl border border-[var(--border-color)] flex items-center gap-3 cursor-pointer <?= $type=='failure'?'border-red-500 bg-red-50/50':'' ?>">
                            <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
                            <div><span class="text-[10px] font-bold text-red-500 block">FAILURE</span><span class="text-xl font-black text-red-500"><?= $stat_fail ?></span></div>
                        </div>
                    </div>
                    <div class="text-[10px] font-bold text-right text-[var(--text-sub)] opacity-70">
                        <div><i class="fas fa-microchip"></i> <?= $t['last_run'] ?>: <?= $last_run ?></div>
                        <div><i class="far fa-clock"></i> <?= $t['next_run'] ?>: <?= $next_run ?></div>
                    </div>
                </div>

                <div class="bg-[var(--card-bg)] border border-[var(--border-color)] rounded-xl p-2 pl-4 flex items-center gap-2 shadow-sm">
                    <div class="flex items-center gap-2 px-2">
                        <input type="checkbox" id="selectAll" class="w-4 h-4 rounded accent-[#18aee7] cursor-pointer">
                        <label for="selectAll" class="text-[11px] font-black text-[var(--text-sub)] cursor-pointer ml-1 whitespace-nowrap"><?= $t['select_all'] ?></label>
                    </div>
                    <div class="search-divider"></div>
                    <select id="searchGroup" onchange="triggerSearch()" class="bg-transparent text-sm font-bold outline-none cursor-pointer flex-[0.7] text-[var(--text-main)]">
                        <option value="all"><?= $t['group_all'] ?></option>
                        <?php foreach($existing_groups as $g): ?>
                            <option value="<?=htmlspecialchars($g)?>" <?= $search_group==$g?'selected':'' ?>><?=htmlspecialchars($g)?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="search-divider"></div>
                    <input type="text" id="searchCompany" value="<?=htmlspecialchars($search_company)?>" class="w-full bg-transparent p-2 text-sm outline-none flex-[1.5] text-[var(--text-main)]" placeholder="<?= $t['search_company_ph'] ?>" onkeypress="if(event.keyCode==13) triggerSearch()">
                    <button onclick="triggerSearch()" class="px-4 py-2 bg-[var(--bg-color)] border border-[var(--border-color)] hover:bg-[#18aee7] hover:text-white rounded-lg text-sm font-bold transition-colors"><?= $t['search_btn'] ?></button>
                    <div class="search-divider"></div>

                    <select id="filterStatus" onchange="triggerSearch()" class="bg-transparent text-sm font-bold outline-none cursor-pointer text-[var(--text-main)]">
                        <option value="all" <?= $filter_status=='all'?'selected':'' ?>><?= $t['filter_all'] ?></option>
                        <option disabled>──────────</option> 
                        <option value="alarm" <?= $filter_status=='alarm'?'selected':'' ?>><?= $t['status_alarm'] ?></option>
                        <option value="no alarm" <?= $filter_status=='no alarm'?'selected':'' ?>><?= $t['status_noalarm'] ?></option>
                        <option value="disable" <?= $filter_status=='disable'?'selected':'' ?>><?= $t['status_disable'] ?></option>
                        <option disabled>──────────</option> 
                        <option value="timeout_custom" <?= $filter_status=='timeout_custom'?'selected':'' ?>><?= $t['filter_timeout_custom'] ?></option>
                        <option value="timeout_default" <?= $filter_status=='timeout_default'?'selected':'' ?>><?= $t['filter_timeout_default'] ?></option>
                    </select>

                    <select id="filterEngine" onchange="triggerSearch()" class="bg-transparent text-sm font-bold outline-none cursor-pointer text-[var(--text-main)]">
                        <option value="all" <?= (!isset($_GET['filter_engine']) || $_GET['filter_engine'] == 'all') ? 'selected' : '' ?>><?= $current_lang == 'KO' ? '모든 엔진' : 'All Engines' ?></option>
                        <option value="lynx" <?= (isset($_GET['filter_engine']) && $_GET['filter_engine'] == 'lynx') ? 'selected' : '' ?>><?= $current_lang == 'KO' ? 'LYNX 엔진' : 'LYNX Engine' ?></option>
                        <option value="curl" <?= (isset($_GET['filter_engine']) && $_GET['filter_engine'] == 'curl') ? 'selected' : '' ?>><?= $current_lang == 'KO' ? 'CURL 엔진' : 'CURL Engine' ?></option>
                    </select>

                    <div class="search-divider"></div>
                    
                    <select id="sortOrder" onchange="triggerSearch()" class="bg-transparent text-sm font-bold outline-none cursor-pointer text-[var(--text-main)] pr-2">
                        <option value="latest" <?= $sort=='latest'?'selected':'' ?>><?= $t['sort_latest'] ?></option>
                        <option disabled>──────────</option>
                        <option value="comp_asc" <?= $sort=='comp_asc'?'selected':'' ?>><?= $t['sort_comp_asc'] ?></option>
                        <option value="comp_desc" <?= $sort=='comp_desc'?'selected':'' ?>><?= $t['sort_comp_desc'] ?></option>
                        <option disabled>──────────</option>
                        <option value="retry_desc" <?= $sort=='retry_desc'?'selected':'' ?>><?= $t['sort_retry_desc'] ?? '체크 횟수 내림차순' ?></option>
                        <option value="retry_asc" <?= $sort=='retry_asc'?'selected':'' ?>><?= $t['sort_retry_asc'] ?? '체크 횟수 오름차순' ?></option>
                        <option disabled>──────────</option>
                        <option value="status_desc" <?= $sort=='status_desc'?'selected':'' ?>><?= $t['sort_status_desc'] ?></option>
                        <option value="status_asc" <?= $sort=='status_asc'?'selected':'' ?>><?= $t['sort_status_asc'] ?></option>
                        <option disabled>──────────</option>	
                        <option value="result_succ" <?= $sort=='result_succ'?'selected':'' ?>><?= $t['sort_result_succ'] ?></option>
                        <option value="result_fail" <?= $sort=='result_fail'?'selected':'' ?>><?= $t['sort_result_fail'] ?></option>
                        <option disabled>──────────</option>
                        <option value="ssl" <?= $sort=='ssl'?'selected':'' ?>><?= $t['sort_ssl'] ?></option>
                        <option value="dom" <?= $sort=='dom'?'selected':'' ?>><?= $t['sort_dom'] ?></option>
                    </select>
                </div>
            </div>
        </div>

        <div id="listHeaderWrapper" class="w-full px-8 bg-[var(--bg-color)] pb-2 <?= $current_view == 'card' ? 'hidden' : '' ?>">
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
    </div>

    <div class="w-full px-8 pb-20 pt-2">
        <div class="max-w-7xl mx-auto">
            <form id="bulkForm" method="POST" action="website_status.php">
                <input type="hidden" name="action" id="bulkAction" value="">
                <input type="hidden" name="status" id="bulkStatusValue" value="">
                <input type="hidden" name="ids" id="bulkIds" value="">
                <input type="hidden" name="current_query" id="bulkCurrentQuery" value="">
                
                <div id="mainForm" class="<?= $current_view == 'card' ? 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6' : 'view-list' ?>">
                    <?php 
                    if($result && $result->num_rows > 0) {
                        while($row = $result->fetch_assoc()): 
                            $is_disabled = ($row['check'] == 'disable');
                            
                            // 스케줄 상태 판별
                            $is_scheduled = (($row['sched_enabled'] ?? 0) == 1);
                            $is_currently_off_schedule = false;
                            if ($is_scheduled && !$is_disabled) {
                                if (!function_exists('is_in_schedule_range')) {
                                    function is_in_schedule_range($start_str, $end_str) {
                                        $now = new DateTime();
                                        $now_time = $now->format('H:i');
                                        $start = trim($start_str);
                                        $end = trim($end_str);
                                        if ($start <= $end) {
                                            return ($now_time >= $start && $now_time <= $end);
                                        } else {
                                            return ($now_time >= $start || $now_time <= $end);
                                        }
                                    }
                                }
                                $in_range = is_in_schedule_range($row['sched_start_time'] ?? '09:00', $row['sched_end_time'] ?? '18:00');
                                $mode = $row['sched_mode'] ?? 'active';
                                if ($mode == 'active' && !$in_range) {
                                    $is_currently_off_schedule = true;
                                } elseif ($mode == 'mute' && $in_range) {
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
                    
                    <div class="monitor-item relative bg-[var(--card-bg)] overflow-hidden transition-all border border-[var(--border-color)] rounded-xl <?= $is_disabled ? 'opacity-60' : '' ?> hover:shadow-md">
                        <div class="absolute top-0 left-0 w-1.5 h-full <?= $border_color ?>"></div>

                        <?php include 'views/components/item_card.php'; ?>
                        <?php include 'views/components/item_list.php'; ?>
                    </div>
                    <?php 
                        endwhile; 
                    } else {
                        echo "<div class='col-span-full text-center py-10 text-[var(--text-sub)]'>데이터가 없습니다.</div>";
                    }
                    ?>
                </div>
            </form>
        </div>
    </div>

    </div>

    <!-- ⚙️ 환경 설정 모달 ────────────────────────────────────────────── -->
    <div id="settingsModal" class="fixed inset-0 z-[1000] hidden items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-[var(--card-bg)] border border-[var(--border-color)] w-full max-w-2xl rounded-2xl shadow-2xl flex flex-col overflow-hidden max-h-[90vh]">
            <div class="px-6 py-4 border-b border-[var(--border-color)] flex justify-between items-center bg-slate-50 dark:bg-slate-800/50">
                <h3 class="text-base font-black flex items-center gap-2"><i class="fas fa-sliders text-[#18aee7]"></i> <?= $current_lang == 'KO' ? '모니터링 프로그램 환경 설정' : 'System Configuration' ?></h3>
                <i class="fas fa-times cursor-pointer hover:text-red-500" onclick="closeSettingsModal()"></i>
            </div>

            <!-- 탭 버튼 헤더 -->
            <div class="flex border-b border-[var(--border-color)] bg-slate-50 dark:bg-slate-800/50">
                <button type="button" onclick="switchSettingsTab('sys')" id="tabBtn-sys" class="flex-1 py-3 text-xs font-bold border-b-2 border-[#18aee7] text-[#18aee7] focus:outline-none">
                    <?= $current_lang == 'KO' ? '시스템 연동 설정' : 'System Config' ?>
                </button>
                <button type="button" onclick="switchSettingsTab('def')" id="tabBtn-def" class="flex-1 py-3 text-xs font-bold border-b-2 border-transparent text-[var(--text-sub)] hover:text-[var(--text-main)] focus:outline-none">
                    <?= $current_lang == 'KO' ? '신규 항목 기본값 설정' : 'Default Monitoring Values' ?>
                </button>
            </div>
            
            <form action="save_settings.php" method="POST" class="flex-1 overflow-y-auto p-6 flex flex-col gap-6 text-sm">
                <!-- 탭 1: 시스템 설정 -->
                <div id="tabContent-sys" class="flex flex-col gap-6">
                    <!-- 1. 텔레그램 설정 -->
                    <div class="border border-[var(--border-color)] rounded-xl p-4 bg-slate-50/30 dark:bg-slate-800/10">
                        <div class="flex justify-between items-center mb-3">
                            <h4 class="font-bold flex items-center gap-2 text-blue-500"><i class="fab fa-telegram text-lg"></i> <?= $current_lang == 'KO' ? '텔레그램 알림 설정' : 'Telegram Alert Settings' ?></h4>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="tg_enabled" value="1" class="sr-only peer" <?= ($settings['tg_enabled'] ?? '0') == '1' ? 'checked' : '' ?>>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-[#18aee7]"></div>
                            </label>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-[var(--text-sub)] mb-1">Bot Token</label>
                                <input type="password" name="tg_token" value="<?= htmlspecialchars($settings['tg_token'] ?? '') ?>" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]" placeholder="토큰 입력">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-[var(--text-sub)] mb-1">Chat ID</label>
                                <input type="text" name="tg_chat_id" value="<?= htmlspecialchars($settings['tg_chat_id'] ?? '') ?>" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]" placeholder="챗 ID 입력">
                            </div>
                        </div>
                    </div>

                    <!-- DB 비밀번호 변경 및 로그 보존 설정 -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- DB 비밀번호 변경 -->
                        <div class="border border-[var(--border-color)] rounded-xl p-4 bg-slate-50/30 dark:bg-slate-800/10 flex flex-col justify-between">
                            <div>
                                <h4 class="font-bold flex items-center gap-2 text-red-500 mb-3"><i class="fas fa-key"></i> <?= $current_lang == 'KO' ? 'DB 비밀번호 변경' : 'Change DB Password' ?></h4>
                                <p class="text-[11px] text-[var(--text-sub)] mb-3"><?= $current_lang == 'KO' ? '이전 비밀번호와 변경할 새 비밀번호를 입력하여 DB 비밀번호를 변경합니다.' : 'Change database password by providing the current and new password.' ?></p>
                            </div>
                            <div class="space-y-2">
                                <div>
                                    <label class="block text-[10px] font-bold text-[var(--text-sub)] mb-0.5"><?= $current_lang == 'KO' ? '이전 DB 패스워드' : 'Current DB Password' ?></label>
                                    <input type="password" name="db_pass_old" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]" placeholder="<?= $current_lang == 'KO' ? '이전 패스워드 입력' : 'Enter current password' ?>">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-[var(--text-sub)] mb-0.5"><?= $current_lang == 'KO' ? '변경할 패스워드' : 'New DB Password' ?></label>
                                    <input type="password" name="db_pass_new" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]" placeholder="<?= $current_lang == 'KO' ? '변경할 패스워드 입력' : 'Enter new password' ?>">
                                </div>
                            </div>
                        </div>

                        <!-- 로그 보존 기간 설정 -->
                        <div class="border border-[var(--border-color)] rounded-xl p-4 bg-slate-50/30 dark:bg-slate-800/10 flex flex-col justify-between">
                            <div>
                                <h4 class="font-bold flex items-center gap-2 text-green-500 mb-3"><i class="fas fa-hdd"></i> <?= $current_lang == 'KO' ? '로그 보관 기간 설정' : 'Data Retention settings' ?></h4>
                                <p class="text-[11px] text-[var(--text-sub)] mb-2"><?= $current_lang == 'KO' ? '시계열 응답 기록 데이터를 보관할 일수입니다. 기간이 경과하면 자동 정리됩니다.' : 'Number of days to keep response logs in DB. Old logs are cleared.' ?></p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '보존 기간 (일)' : 'Retention (Days)' ?></label>
                                <input type="number" name="retention_days" min="1" max="365" value="<?= htmlspecialchars($settings['retention_days'] ?? '30') ?>" class="w-24 bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 탭 2: 신규 등록 기본값 설정 -->
                <div id="tabContent-def" class="hidden flex flex-col gap-6">
                    <div class="border border-[var(--border-color)] rounded-xl p-4 bg-slate-50/30 dark:bg-slate-800/10">
                        <h4 class="font-bold flex items-center gap-2 text-sky-500 mb-4"><i class="fas fa-sliders-h"></i> <?= $current_lang == 'KO' ? '모니터링 항목 기본값' : 'Default Monitoring Settings' ?></h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- 실행 엔진 -->
                            <div>
                                <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '기본 실행 엔진' : 'Default Engine' ?></label>
                                <select name="def_engine_type" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]">
                                    <option value="lynx" <?= ($settings['def_engine_type'] ?? 'lynx') == 'lynx' ? 'selected' : '' ?>>LYNX (텍스트 추출 방식)</option>
                                    <option value="curl" <?= ($settings['def_engine_type'] ?? 'lynx') == 'curl' ? 'selected' : '' ?>>CURL (Raw HTML 방식)</option>
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

                            <!-- SSL, DOMAIN 체크박스 -->
                            <div class="col-span-1 md:col-span-2 flex gap-6 py-2 border-t border-dashed border-[var(--border-color)] mt-2">
                                <label class="flex items-center gap-2 cursor-pointer font-bold text-xs text-[var(--text-sub)]">
                                    <input type="checkbox" name="def_ssl_mon" value="1" class="w-4 h-4 rounded accent-[#18aee7]" <?= ($settings['def_ssl_mon'] ?? '1') == '1' ? 'checked' : '' ?>>
                                    <span>SSL EXPIRY 감지 기본 활성화</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer font-bold text-xs text-[var(--text-sub)]">
                                    <input type="checkbox" name="def_dom_mon" value="1" class="w-4 h-4 rounded accent-[#18aee7]" <?= ($settings['def_dom_mon'] ?? '1') == '1' ? 'checked' : '' ?>>
                                    <span>DOMAIN EXPIRY 감지 기본 활성화</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- 기본 이메일 발송 방식 및 주소 -->
                    <div class="border border-[var(--border-color)] rounded-xl p-4 bg-slate-50/30 dark:bg-slate-800/10">
                        <h4 class="font-bold flex items-center gap-2 text-green-500 mb-3"><i class="fas fa-envelope"></i> <?= $current_lang == 'KO' ? '기본 이메일 알림 설정' : 'Default Email Alerts Settings' ?></h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label class="block text-xs font-bold text-[var(--text-sub)] mb-1"><?= $current_lang == 'KO' ? '기본 발송 방식' : 'Default Email Mode' ?></label>
                                <select name="def_email_mode" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]">
                                    <option value="none" <?= ($settings['def_email_mode'] ?? 'none') == 'none' ? 'selected' : '' ?>>미발송 (none)</option>
                                    <option value="postfix" <?= ($settings['def_email_mode'] ?? 'none') == 'postfix' ? 'selected' : '' ?>>로컬 Postfix 직접발송</option>
                                    <option value="smtp" <?= ($settings['def_email_mode'] ?? 'none') == 'smtp' ? 'selected' : '' ?>>외부 SMTP 릴레이발송</option>
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

                        <!-- 💡 외부 SMTP 릴레이 안내 설명 배너 -->
                        <div class="bg-blue-50/50 dark:bg-slate-800/40 border border-blue-200 dark:border-slate-700 rounded-xl p-3 flex gap-2.5 text-xs text-[var(--text-sub)]">
                            <i class="fas fa-info-circle text-blue-500 mt-0.5 text-sm"></i>
                            <div class="leading-relaxed">
                                <strong class="text-[var(--text-main)] block mb-1">외부 SMTP 릴레이 발송 시 연동 안내</strong>
                                <span>이메일 발송 방식을 <strong>'외부 SMTP 릴레이발송'</strong>으로 적용할 경우, 보안 및 계정 유출 방지를 위해 SMTP 호스트/포트/접속 계정 비밀번호는 시스템 소스 코드나 DB 대신 컨테이너 환경설정 파일(프로젝트 루트의 <code class="bg-gray-150 dark:bg-slate-700 px-1 py-0.5 rounded text-[10px]">.env</code>) 내의 변수(<code class="text-[10px]">SMTP_RELAYHOST</code>, <code class="text-[10px]">SMTP_USER</code>, <code class="text-[10px]">SMTP_PASS</code>)로부터 로드해 백그라운드 스케줄러가 안전하게 발송을 수행합니다.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-4 border-t border-[var(--border-color)] pt-4">
                    <button type="button" onclick="closeSettingsModal()" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 dark:bg-slate-800 dark:hover:bg-slate-700 rounded-xl font-bold transition-all text-xs"><?= $current_lang == 'KO' ? '취소' : 'Cancel' ?></button>
                    <button type="submit" class="px-6 py-2.5 bg-[#18aee7] hover:bg-[#159bd0] text-white rounded-xl font-bold shadow-md transition-all text-xs"><?= $current_lang == 'KO' ? '설정 저장' : 'Save Settings' ?></button>
                </div>
            </form>
        </div>
    </div>
        </div>
    </div>

    <!-- 🔔 개별 모니터링 스케줄 설정 모달 ────────────────────────────── -->
    <div id="itemScheduleModal" class="fixed inset-0 z-[1000] hidden items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-[var(--card-bg)] border border-[var(--border-color)] w-full max-w-md rounded-2xl shadow-2xl flex flex-col overflow-hidden">
            <div class="px-6 py-4 border-b border-[var(--border-color)] flex justify-between items-center bg-slate-50 dark:bg-slate-800/50">
                <h3 class="text-base font-black flex items-center gap-2"><i class="fas fa-clock text-[#18aee7]"></i> <span id="schedModalTitle">모니터링 스케줄 설정</span></h3>
                <i class="fas fa-times cursor-pointer hover:text-red-500" onclick="closeItemScheduleModal()"></i>
            </div>
            
            <form action="save_item_schedule.php" method="POST" class="p-6 flex flex-col gap-4 text-sm">
                <input type="hidden" name="no" id="schedItemNo" value="">
                
                <!-- 스케줄 활성화 토글 -->
                <div class="flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/20 p-3 rounded-xl border border-[var(--border-color)]">
                    <div>
                        <span class="font-bold block">개별 스케줄링 사용</span>
                        <span class="text-[10px] text-[var(--text-sub)]">지정한 시간에만 알림을 보내거나 무음 처리합니다.</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="sched_enabled" id="schedEnabled" value="1" class="sr-only peer" onchange="toggleSchedFields(this.checked)">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-[#18aee7]"></div>
                    </label>
                </div>

                <div id="schedFields" class="flex flex-col gap-4 transition-all duration-300">
                    <!-- 동작 모드 -->
                    <div>
                        <label class="block text-xs font-bold text-[var(--text-sub)] mb-1.5">스케줄 동작 방식</label>
                        <div class="flex bg-[var(--bg-color)] border border-[var(--border-color)] rounded-xl p-1 h-[42px]">
                            <label class="flex-1 cursor-pointer h-full">
                                <input type="radio" name="sched_mode" id="schedModeActive" value="active" class="hidden peer" checked>
                                <div class="h-full flex items-center justify-center text-xs font-bold rounded-lg peer-checked:bg-[#18aee7] peer-checked:text-white transition-all cursor-pointer">활성화 (지정 시간대 모니터링)</div>
                            </label>
                            <label class="flex-1 cursor-pointer h-full">
                                <input type="radio" name="sched_mode" id="schedModeMute" value="mute" class="hidden peer">
                                <div class="h-full flex items-center justify-center text-xs font-bold rounded-lg peer-checked:bg-[#18aee7] peer-checked:text-white transition-all cursor-pointer">비활성화 (지정 시간대 무음)</div>
                            </label>
                        </div>
                    </div>

                    <!-- 시간 범위 -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-[var(--text-sub)] mb-1">시작 시각</label>
                            <input type="time" name="sched_start_time" id="schedStartTime" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-[var(--text-sub)] mb-1">종료 시각</label>
                            <input type="time" name="sched_end_time" id="schedEndTime" class="w-full bg-[var(--bg-color)] border border-[var(--border-color)] rounded-lg p-2 text-xs outline-none focus:border-[#18aee7]">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-4 border-t border-[var(--border-color)] pt-4">
                    <button type="button" onclick="closeItemScheduleModal()" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 dark:bg-slate-800 dark:hover:bg-slate-700 rounded-xl font-bold transition-all text-xs">취소</button>
                    <button type="submit" class="px-6 py-2.5 bg-[#18aee7] hover:bg-[#159bd0] text-white rounded-xl font-bold shadow-md transition-all text-xs">스케줄 저장</button>
                </div>
            </form>
        </div>
    </div>

    <?php include 'views/components/modal_form.php'; ?>

    <script src="../assets/js/script.js?v=<?=time()?>"></script>

    <!-- 📊 아코디언 및 시계열 차트 로직 추가 -->
    <script>
        const charts = {};

        function toggleAccordion(event, no) {
            if (event) event.stopPropagation();
            const accordion = document.getElementById('accordion-' + no);
            const arrow = document.getElementById('arrow-' + no);
            if (!accordion) return;

            if (accordion.classList.contains('hidden')) {
                accordion.classList.remove('hidden');
                accordion.classList.add('flex');
                arrow.style.transform = 'rotate(180deg)';
                loadChart(no, '1d');
            } else {
                accordion.classList.remove('flex');
                accordion.classList.add('hidden');
                arrow.style.transform = 'rotate(0deg)';
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
                    } else {
                        return (dt.getMonth() + 1) + '/' + dt.getDate() + ' ' + dt.getHours() + 'h';
                    }
                });
                const responseTimes = data.map(item => item.response_time);
                const results = data.map(item => item.result);

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
                const textColor = isDark ? '#94a3b8' : '#64748b';
                const gridColor = isDark ? '#334155' : '#e2e8f0';

                charts[no] = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Response Time (s)',
                            data: responseTimes,
                            borderColor: '#18aee7',
                            backgroundColor: 'rgba(24, 174, 231, 0.08)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.3,
                            pointRadius: labels.length > 50 ? 0 : 1,
                            pointHoverRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
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
                                beginAtZero: true,
                                grid: { color: gridColor },
                                ticks: {
                                    color: textColor,
                                    font: { size: 9 }
                                }
                            }
                        }
                    }
                });

            } catch (err) {
                console.error('Failed to load chart data:', err);
            }
        }

        async function loadCustomChart(no) {
            const startVal = document.getElementById('chart-start-' + no).value;
            const endVal = document.getElementById('chart-end-' + no).value;
            if (!startVal || !endVal) {
                alert('<?= $current_lang == "KO" ? "시작일과 종료일을 모두 선택해주세요." : "Please select both start and end dates." ?>');
                return;
            }
            
            // 날짜 논리 검증 (시작일이 종료일보다 늦을 경우)
            if (new Date(startVal) > new Date(endVal)) {
                alert('<?= $current_lang == "KO" ? "시작일은 종료일보다 이전 날짜여야 합니다." : "The start date must be before the end date." ?>');
                return;
            }
            
            // 미래 날짜 제한 검증
            const today = new Date().toISOString().split('T')[0];
            if (startVal > today || endVal > today) {
                alert('<?= $current_lang == "KO" ? "오늘 이후의 날짜는 조회할 수 없습니다." : "You cannot query dates in the future." ?>');
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
            document.getElementById('settingsModal').classList.remove('hidden');
            document.getElementById('settingsModal').classList.add('flex');
        }

        function closeSettingsModal() {
            document.getElementById('settingsModal').classList.remove('flex');
            document.getElementById('settingsModal').classList.add('hidden');
        }

        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('settings_saved')) {
                alert('<?= $current_lang == "KO" ? "설정이 성공적으로 저장되었습니다." : "Settings saved successfully." ?>');
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
                }
                alert(msg);
                const newUrl = window.location.pathname + window.location.search.replace(/[?&]settings_error=[^&]+/, '').replace(/^&/, '?').replace(/\?$/, '');
                window.history.replaceState({}, document.title, newUrl);
            }
        });

        function openScheduleModal(event, row) {
            if (event) event.stopPropagation();
            document.getElementById('schedItemNo').value = row.no;
            document.getElementById('schedModalTitle').innerText = `${row.company} 스케줄 설정`;
            
            const enabled = parseInt(row.sched_enabled || 0) === 1;
            document.getElementById('schedEnabled').checked = enabled;
            
            const mode = row.sched_mode || 'active';
            if (mode === 'active') {
                document.getElementById('schedModeActive').checked = true;
            } else {
                document.getElementById('schedModeMute').checked = true;
            }
            
            // sched_start_time과 sched_end_time에서 HH:MM만 추출
            const start = row.sched_start_time ? row.sched_start_time.substring(0, 5) : '09:00';
            const end = row.sched_end_time ? row.sched_end_time.substring(0, 5) : '18:00';
            
            document.getElementById('schedStartTime').value = start;
            document.getElementById('schedEndTime').value = end;
            
            toggleSchedFields(enabled);
            
            document.getElementById('itemScheduleModal').classList.remove('hidden');
            document.getElementById('itemScheduleModal').classList.add('flex');
        }

        function closeItemScheduleModal() {
            document.getElementById('itemScheduleModal').classList.remove('flex');
            document.getElementById('itemScheduleModal').classList.add('hidden');
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