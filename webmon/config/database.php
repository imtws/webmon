<?php
// DB 연결
$db = new mysqli("localhost", "sitemonitor", "ncloud249302", "sitemonitor_new");
if ($db->connect_error) { 
    die("DB 연결 실패: " . $db->connect_error); 
}
?>