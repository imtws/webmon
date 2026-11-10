<?php
// =============================================
// DB 연결 - 환경변수 기반 (Docker/보안 대응)
// =============================================

$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'sitemonitor';
$db_pass = getenv('DB_PASS') ?: '';
$db_name = getenv('DB_NAME') ?: 'sitemonitor';
$db_port = (int)(getenv('DB_PORT') ?: 3306);

$db = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

if ($db->connect_error) {
    // 프로덕션에서는 상세 에러 노출 금지
    $debug = getenv('APP_DEBUG') === 'true';
    if ($debug) {
        die("DB 연결 실패: " . $db->connect_error);
    } else {
        die("서비스 연결에 실패했습니다. 관리자에게 문의하세요.");
    }
}

$db->set_charset('utf8mb4');

// 시스템 설정 로드
$settings = [];
$res = $db->query("SELECT key_name, key_value FROM system_settings");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $settings[$row['key_name']] = $row['key_value'];
    }
}

// .env 설정 로딩 및 데이터 병합 (민감정보 전용)
require_once __DIR__ . '/../classes/Utils.php';
$env_settings = Utils::readEnv();
foreach ($env_settings as $k => $v) {
    if ($k === 'TG_TOKEN') $settings['tg_token'] = $v;
    elseif ($k === 'TG_CHAT_ID') $settings['tg_chat_id'] = $v;
    elseif ($k === 'SMTP_HOST') $settings['smtp_host'] = $v;
    elseif ($k === 'SMTP_PORT') $settings['smtp_port'] = $v;
    elseif ($k === 'SMTP_USER') $settings['smtp_user'] = $v;
    elseif ($k === 'SMTP_PASS') $settings['smtp_pass'] = $v;
    elseif ($k === 'SMTP_SECURE') $settings['smtp_secure'] = $v;
    elseif ($k === 'WEBMON_URL') $settings['webmon_url'] = $v;
}
?>