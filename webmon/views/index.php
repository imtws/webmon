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
    
    <link rel="stylesheet" href="../assets/css/style.css?v=<?=time()?>">
    <script>
        tailwind.config = { darkMode: 'class' };
        const langPack = <?= isset($js_lang) ? $js_lang : '{}' ?>;
    </script>
</head>
<body class="m-0 p-0 text-[var(--text-main)] transition-colors duration-200">

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
                    <div class="w-20 text-right pr-2"><?= $t['h_setting'] ?></div>
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
                            $border_color = $is_disabled ? 'bg-gray-400' : ($row['value'] > 0 ? 'bg-[#18aee7]' : 'bg-red-500');
                            $status_class = ($row['check'] == 'alarm') ? "text-green-500" : (($row['check'] == 'no alarm') ? "text-orange-500" : "text-gray-400");
                            $check_text = ($row['check'] == 'alarm') ? $t['status_alarm'] : (($row['check'] == 'no alarm') ? $t['status_noalarm'] : $t['status_disable']);
                            $result_text = $row['value'] > 0 ? $t['v_success'] : $t['v_fail'];
                            $result_color = $row['value'] > 0 ? 'text-green-500' : 'text-red-500';
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

    <?php include 'views/components/modal_form.php'; ?>

    <script src="../assets/js/script.js?v=<?=time()?>"></script>
</body>
</html>