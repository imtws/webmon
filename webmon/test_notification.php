<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Utils.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respondTest(int $status, bool $success, string $message, array $details = []): void {
    http_response_code($status);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $details), JSON_UNESCAPED_UNICODE);
    exit;
}

function readSmtpReply($socket): array {
    $reply = '';
    do {
        $line = fgets($socket, 2048);
        if ($line === false) {
            throw new RuntimeException('The SMTP server closed the connection.');
        }
        $reply .= $line;
    } while (isset($line[3]) && $line[3] === '-');

    $code = (int)substr($reply, 0, 3);
    return [$code, trim($reply)];
}

function sendSmtpCommand($socket, string $command, array $expected): string {
    if (fwrite($socket, $command . "\r\n") === false) {
        throw new RuntimeException('Could not write to the SMTP server.');
    }
    [$code, $reply] = readSmtpReply($socket);
    if (!in_array($code, $expected, true)) {
        throw new RuntimeException("SMTP server returned {$code}.");
    }
    return $reply;
}

function sendTestEmail(
    string $host,
    int $port,
    string $secure,
    string $username,
    string $password,
    string $sender,
    string $recipient
): void {
    $transport = $secure === 'ssl' ? 'ssl://' : 'tcp://';
    $context = stream_context_create(['ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
        'peer_name' => $host
    ]]);
    $socket = @stream_socket_client(
        $transport . $host . ':' . $port,
        $errorCode,
        $errorMessage,
        10,
        STREAM_CLIENT_CONNECT,
        $context
    );
    if (!$socket) {
        throw new RuntimeException('Could not connect to the SMTP server.');
    }
    stream_set_timeout($socket, 10);

    try {
        [$code] = readSmtpReply($socket);
        if ($code !== 220) {
            throw new RuntimeException("SMTP server returned {$code} at greeting.");
        }

        $clientName = preg_replace('/[^A-Za-z0-9.-]/', '', $_SERVER['SERVER_NAME'] ?? 'webmon');
        sendSmtpCommand($socket, 'EHLO ' . ($clientName ?: 'webmon'), [250]);

        if ($secure === 'tls') {
            sendSmtpCommand($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Could not establish TLS with the SMTP server.');
            }
            sendSmtpCommand($socket, 'EHLO ' . ($clientName ?: 'webmon'), [250]);
        }

        if ($username !== '') {
            sendSmtpCommand($socket, 'AUTH LOGIN', [334]);
            sendSmtpCommand($socket, base64_encode($username), [334]);
            sendSmtpCommand($socket, base64_encode($password), [235]);
        }

        sendSmtpCommand($socket, 'MAIL FROM:<' . $sender . '>', [250]);
        sendSmtpCommand($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
        sendSmtpCommand($socket, 'DATA', [354]);

        $subject = 'WebMon 알림 설정 테스트';
        $body = "WebMon 테스트 메일입니다.\r\n이 메시지가 도착했다면 이메일 알림 설정이 정상입니다.";
        $message = implode("\r\n", [
            'From: <' . $sender . '>',
            'To: <' . $recipient . '>',
            'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8'),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            $body
        ]);
        $message = preg_replace('/(?m)^\./', '..', $message);
        if (fwrite($socket, $message . "\r\n.\r\n") === false) {
            throw new RuntimeException('Could not submit the test email.');
        }
        [$code] = readSmtpReply($socket);
        if ($code !== 250) {
            throw new RuntimeException("SMTP server returned {$code} after message submission.");
        }
        sendSmtpCommand($socket, 'QUIT', [221]);
    } finally {
        fclose($socket);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest'
    || stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
    respondTest(405, false, 'Use the settings page to run a test.');
}

$origin = parse_url($_SERVER['HTTP_ORIGIN'] ?? '', PHP_URL_HOST);
$requestHost = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
if (!$origin || strcasecmp($origin, $requestHost) !== 0) {
    respondTest(403, false, 'The test request origin could not be verified.');
}

$rawInput = file_get_contents('php://input');
if ($rawInput === false || strlen($rawInput) > 16384) {
    respondTest(413, false, 'The test request is invalid or too large.');
}
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    respondTest(400, false, 'The test request is invalid.');
}

$channel = $input['channel'] ?? '';
if ($channel === 'telegram') {
    $token = trim((string)($input['tg_token'] ?? ''));
    $chatId = trim((string)($input['tg_chat_id'] ?? ''));
    if (!preg_match('/^\d+:[A-Za-z0-9_-]+$/', $token) || $chatId === '') {
        respondTest(422, false, 'Enter a valid Bot Token and Chat ID first.');
    }

    $curl = curl_init('https://api.telegram.org/bot' . $token . '/sendMessage');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'chat_id' => $chatId,
            'text' => "WebMon 알림 설정 테스트입니다.\n이 메시지가 도착했다면 텔레그램 연동이 정상입니다."
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    $responseBody = curl_exec($curl);
    $httpStatus = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $curlError = curl_errno($curl);
    curl_close($curl);

    if ($responseBody === false || $curlError !== 0) {
        respondTest(502, false, 'Could not connect to the Telegram API.');
    }
    $telegramResponse = json_decode($responseBody, true);
    if ($httpStatus !== 200 || !is_array($telegramResponse) || empty($telegramResponse['ok'])) {
        $description = is_array($telegramResponse) ? ($telegramResponse['description'] ?? '') : '';
        $safeDescription = preg_replace('/[\r\n]+/', ' ', (string)$description);
        respondTest(502, false, $safeDescription !== ''
            ? 'Telegram rejected the test: ' . $safeDescription
            : 'Telegram rejected the test (HTTP ' . $httpStatus . ').');
    }
    respondTest(200, true, 'Test message sent to Telegram.');
}

if ($channel !== 'email') {
    respondTest(400, false, 'Choose a supported notification channel.');
}

$recipient = trim((string)($input['recipient'] ?? ''));
if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    respondTest(422, false, 'Enter a valid test recipient email address.');
}

$settings = [];
$settingsResult = $db->query('SELECT key_name, key_value FROM system_settings');
if ($settingsResult) {
    while ($row = $settingsResult->fetch_assoc()) {
        $settings[$row['key_name']] = $row['key_value'] ?? '';
    }
}
$runtimeSettings = Utils::readEnv();
$settings['smtp_host'] = $runtimeSettings['SMTP_HOST'] ?? '';
$settings['smtp_port'] = $runtimeSettings['SMTP_PORT'] ?? '587';
$settings['smtp_user'] = $runtimeSettings['SMTP_USER'] ?? '';
$settings['smtp_pass'] = $runtimeSettings['SMTP_PASS'] ?? '';
$settings['smtp_secure'] = $runtimeSettings['SMTP_SECURE'] ?? 'tls';

$sender = trim((string)($input['sender'] ?? ''));
if ($sender === '') {
    $sender = trim((string)($settings['global_sender_email'] ?? ''));
}

if (!filter_var($sender, FILTER_VALIDATE_EMAIL)) {
    respondTest(422, false, 'Enter a valid sender address first.');
}

$relayEnabled = ($settings['smtp_mode'] ?? 'direct') === 'relay';
$host = 'mail';
$port = 25;
$secure = 'none';
$username = '';
$password = '';

if ($relayEnabled) {
    $host = trim((string)($settings['smtp_host'] ?? ''));
    $portValue = (string)($settings['smtp_port'] ?? '');
    $port = (int)$portValue;
    $secure = (string)($settings['smtp_secure'] ?? 'tls');
    $username = (string)($settings['smtp_user'] ?? '');
    $password = (string)($settings['smtp_pass'] ?? '');
    if ($host === '' || preg_match('/[\r\n\s]/', $host) || $port < 1 || $port > 65535) {
        respondTest(422, false, 'Check the SMTP relay host and port.');
    }
    if (!in_array($secure, ['none', 'tls', 'ssl'], true)) {
        respondTest(422, false, 'Choose a valid SMTP security mode.');
    }
    if ($username !== '' && $password === '') {
        respondTest(422, false, 'Enter the SMTP password or save the relay settings first.');
    }
}

try {
    sendTestEmail($host, $port, $secure, $username, $password, $sender, $recipient);
} catch (Throwable $error) {
    error_log('Notification email test failed: ' . $error->getMessage());
    respondTest(502, false, 'Email test failed: ' . $error->getMessage());
}

respondTest(202, true, $relayEnabled
    ? 'Accepted by the configured SMTP relay; final delivery is not confirmed.'
    : 'Accepted for direct delivery; final recipient delivery is not confirmed.', [
    'delivery_status' => 'queued'
]);
