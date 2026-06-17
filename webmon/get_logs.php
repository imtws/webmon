<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once 'config/database.php';
require_once 'config/language.php';
require_once 'classes/Utils.php';

// 시스템 설정 불러오기
$settings = [];
$settings_res = $db->query("SELECT key_name, key_value FROM system_settings");
if ($settings_res) {
    while ($row = $settings_res->fetch_assoc()) {
        $settings[$row['key_name']] = $row['key_value'];
    }
}

$type = $_GET['type'] ?? 'total';
$search_company = $_GET['search_company'] ?? '';
$search_group = $_GET['search_group'] ?? 'all';
$filter_status = $_GET['filter_status'] ?? 'all';
$filter_engine = $_GET['filter_engine'] ?? 'all';

$log_max_lines = isset($settings['log_max_lines']) ? (int)$settings['log_max_lines'] : 500;

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
$log_query = "
    SELECT STRAIGHT_JOIN l.*, c.group_name, c.company, c.url, c.engine_type
    FROM monitor_log l FORCE INDEX (PRIMARY)
    INNER JOIN monitor_conf c ON l.monitor_conf_no = c.no
    WHERE $log_where_sql
    ORDER BY l.id DESC
    LIMIT $log_max_lines
";
$log_result = $db->query($log_query);

$t = $lang_pack[$current_lang] ?? $lang_pack['KO'];

if (isset($log_result) && $log_result->num_rows > 0) {
    while($log_row = $log_result->fetch_assoc()) {
        $border_color = $log_row['result'] > 0 ? 'bg-[#18aee7]' : 'bg-red-500';
        $result_badge_class = $log_row['result'] > 0 
            ? 'bg-green-50 dark:bg-green-900/20 text-green-500 border border-green-200 dark:border-green-800' 
            : 'bg-red-50 dark:bg-rose-900/20 text-red-500 border border-red-200 dark:border-rose-800';
        $result_text = $log_row['result'] > 0 ? $t['v_success'] : $t['v_fail'];
        $result_icon = $log_row['result'] > 0 ? 'fa-check-circle' : 'fa-exclamation-circle';
        ?>
        <div class="relative bg-[var(--card-bg)] border border-[var(--border-color)] rounded-xl py-3 pr-5 pl-7 flex items-center hover:shadow-md transition-all text-[13px] hover:z-[110]">
            <div class="absolute top-0 left-0 w-1.5 h-full <?= $border_color ?> rounded-l-xl"></div>
            <!-- 시각 -->
            <div class="w-48 text-[var(--text-sub)] font-mono">
                <?= htmlspecialchars($log_row['log_time']) ?>
            </div>
            <!-- 그룹 -->
            <div class="w-40 font-semibold text-slate-500 dark:text-slate-400 truncate pr-4">
                <?= htmlspecialchars($log_row['group_name'] ?: '기본 그룹') ?>
            </div>
            <!-- 업체명 -->
            <div class="w-48 font-black text-[var(--text-main)] truncate pr-4">
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
                                <div class="bg-slate-100 dark:bg-slate-800 text-[10px] text-slate-600 dark:text-slate-400 font-mono rounded px-2 py-1 select-all truncate border border-slate-200 dark:border-slate-700 cursor-help" title="<?= htmlspecialchars($log_row['cmd']) ?>">
                                    <i class="fas fa-terminal text-[8px] mr-1 text-slate-400"></i><?= htmlspecialchars($log_row['cmd']) ?>
                                </div>
                                <div class="absolute bottom-full left-0 z-[120] mb-2 w-max max-w-lg hidden group-hover:block bg-slate-900 text-white text-[10px] rounded p-2 shadow-lg font-mono break-all border border-slate-700 cmd-tooltip">
                                    <?= htmlspecialchars($log_row['cmd']) ?>
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
            <div class="w-28 text-center font-bold font-mono text-slate-700 dark:text-slate-300">
                <?= number_format($log_row['response_time'], 3) ?>s
            </div>
            <!-- 결과 -->
            <div class="w-28 text-center">
                <span class="inline-flex items-center justify-center px-2 py-1 text-[10px] font-black rounded-lg <?= $result_badge_class ?>">
                    <i class="fas <?= $result_icon ?> mr-1 text-[9px]"></i><?= $result_text ?>
                </span>
            </div>
        </div>
        <?php
    }
} else {
    ?>
    <div class="bg-[var(--card-bg)] border border-[var(--border-color)] rounded-xl py-10 text-center text-[var(--text-sub)]">
        <?= $current_lang == 'KO' ? '조회된 로그가 없습니다.' : 'No logs found.' ?>
    </div>
    <?php
}
