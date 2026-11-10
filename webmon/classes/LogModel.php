<?php
require_once __DIR__ . '/Utils.php';

class LogModel {
    /**
     * 로그 조회 - 100만 행 이상 대용량 로그 테이블에 최적화된 쿼리 실행
     *
     * @param mysqli $db
     * @param array $params $_GET 파라미터
     * @param array $settings 시스템 설정
     * @return array ['rows' => array, 'has_more' => bool, 'next_cursor' => int]
     */
    public static function fetchLogs(mysqli $db, array $params, array $settings): array {
        $type = $params['type'] ?? 'total';
        $search_company = $params['search_company'] ?? '';
        $search_group = $params['search_group'] ?? 'all';
        $filter_status = $params['filter_status'] ?? 'all';
        $filter_engine = $params['filter_engine'] ?? 'all';
        
        $log_max_lines = min(100, max(20, (int)($settings['log_max_lines'] ?? 100)));
        $log_before_id = max(0, (int)($params['before_log_id'] ?? 0));
        
        // 1. monitor_conf 테이블에 대한 필터 조건 구성
        $conf_where = ["1=1"];
        $has_conf_filter = false;

        if ($type === 'expiring') {
            $conf_where[] = "`check` != 'disable' AND (
                (ssl_mon = 1 AND ssl_expiry IS NOT NULL AND ssl_expiry <= DATE_ADD(NOW(), INTERVAL 30 DAY))
                OR
                (dom_mon = 1 AND dom_expiry IS NOT NULL AND dom_expiry <= DATE_ADD(NOW(), INTERVAL 30 DAY))
            )";
            $has_conf_filter = true;
        }

        if (!empty($search_company)) {
            $safe_comp = $db->real_escape_string($search_company);
            $regex = Utils::buildChosungRegex($search_company);
            if ($regex !== false) {
                $safe_regex = $db->real_escape_string($regex);
                $conf_where[] = "(company LIKE '%$safe_comp%' OR url LIKE '%$safe_comp%' OR company REGEXP '$safe_regex')";
            } else {
                $conf_where[] = "(company LIKE '%$safe_comp%' OR url LIKE '%$safe_comp%')";
            }
            $has_conf_filter = true;
        }

        if ($search_group !== 'all') {
            $safe_grp = $db->real_escape_string($search_group);
            $conf_where[] = "group_name = '$safe_grp'";
            $has_conf_filter = true;
        }

        if ($filter_status !== 'all') {
            $safe_status = $db->real_escape_string($filter_status);
            if ($safe_status === 'cross_on') {
                $conf_where[] = "cross_check = 1";
            } elseif ($safe_status === 'cross_off') {
                $conf_where[] = "cross_check = 0";
            } elseif ($safe_status === 'timeout_custom') {
                $conf_where[] = "timeout_sec > 2";
            } elseif ($safe_status === 'timeout_default') {
                $conf_where[] = "timeout_sec <= 2";
            } else {
                $conf_where[] = "`check` = '$safe_status'";
            }
            $has_conf_filter = true;
        }

        if ($filter_engine === 'lynx') {
            $conf_where[] = "(engine_type = 'lynx' OR engine_type IS NULL OR engine_type = '')";
            $has_conf_filter = true;
        } elseif ($filter_engine === 'curl') {
            $conf_where[] = "engine_type = 'curl'";
            $has_conf_filter = true;
        }

        // 2. 만약 monitor_conf 필터가 걸려 있다면 대상 설정 ID들을 먼저 조회 (271행 수준이라 0.3ms 소요)
        //    이를 통해 100만 행 monitor_log의 불필요한 풀테이블 스캔을 원천 차단함 (46초 -> 0.02초)
        $conf_id_filter = null;
        if ($has_conf_filter) {
            $conf_sql = "SELECT no FROM monitor_conf WHERE " . implode(' AND ', $conf_where);
            $res = $db->query($conf_sql);
            $matched_ids = [];
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $matched_ids[] = (int)$r['no'];
                }
            }
            // 검색 조건에 맞는 모니터링 항목이 전혀 없다면 로그도 없음 -> 즉시 반환
            if (empty($matched_ids)) {
                return ['rows' => [], 'has_more' => false, 'next_cursor' => 0];
            }
            $conf_id_filter = implode(',', $matched_ids);
        }

        // 3. 로그 테이블 조건 구성
        $log_where = ["1=1"];
        if ($log_before_id > 0) {
            $log_where[] = "l.id < $log_before_id";
        }

        if ($conf_id_filter !== null) {
            $log_where[] = "l.monitor_conf_no IN ($conf_id_filter)";
        }

        if ($type === 'success') {
            $log_where[] = "l.result > 0 AND c.`check` = 'alarm'";
        } elseif ($type === 'failure') {
            $log_where[] = "l.result = 0 AND c.`check` != 'disable'";
        }

        $log_where_sql = implode(' AND ', $log_where);

        // 4. 최적 인덱스 힌트 결정:
        //    모니터 설정 필터가 없는 전체 로그 조회 시에는 PRIMARY KEY 역순 스캔이 가장 빠름 (0.01s)
        //    모니터 설정 ID 필터가 있는 경우에는 idx_conf_no_log_time 인덱스를 타도록 FORCE 거두기
        if (!$has_conf_filter && $type === 'total') {
            $query = "
                SELECT STRAIGHT_JOIN l.*, c.group_name, c.company, c.url, c.engine_type
                FROM monitor_log l FORCE INDEX (PRIMARY)
                INNER JOIN monitor_conf c ON l.monitor_conf_no = c.no
                WHERE $log_where_sql
                ORDER BY l.id DESC
                LIMIT " . ($log_max_lines + 1);
        } else {
            $query = "
                SELECT l.*, c.group_name, c.company, c.url, c.engine_type
                FROM monitor_log l
                INNER JOIN monitor_conf c ON l.monitor_conf_no = c.no
                WHERE $log_where_sql
                ORDER BY l.id DESC
                LIMIT " . ($log_max_lines + 1);
        }

        $log_result = $db->query($query);
        $log_rows = [];
        $log_has_more = $log_result && $log_result->num_rows > $log_max_lines;
        if ($log_result) {
            while ($log_row = $log_result->fetch_assoc()) {
                if (count($log_rows) >= $log_max_lines) break;
                $log_rows[] = $log_row;
            }
        }
        $next_cursor = !empty($log_rows) ? (int)end($log_rows)['id'] : 0;

        return [
            'rows' => $log_rows,
            'has_more' => $log_has_more,
            'next_cursor' => $next_cursor
        ];
    }
}
