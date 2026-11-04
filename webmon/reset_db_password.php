<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (($argv[1] ?? '') !== '--reset-to-configured-password') {
    fwrite(STDERR, "Run this command only through the dedicated maintenance service.\n");
    exit(2);
}

$dbUser = getenv('DB_USER') ?: '';
$dbName = getenv('DB_NAME') ?: '';
$newPassword = getenv('DB_PASS');
$rootPassword = getenv('DB_ROOT_PASS');
$dbPort = (int)(getenv('DB_PORT') ?: 3306);
$runtimeDir = '/var/lib/webmon';
$runtimePasswordFile = $runtimeDir . '/db_pass';

if ($dbUser === '' || $dbName === '' || $newPassword === false || $newPassword === '' || $rootPassword === false || $rootPassword === '') {
    fwrite(STDERR, "Required database recovery configuration is missing.\n");
    exit(1);
}
if (!is_dir($runtimeDir) || !is_writable($runtimeDir)) {
    fwrite(STDERR, "The private runtime credential volume is not writable.\n");
    exit(1);
}

$previousPassword = $newPassword;
if (is_file($runtimePasswordFile)) {
    $storedPassword = file_get_contents($runtimePasswordFile);
    if ($storedPassword === false) {
        fwrite(STDERR, "Could not read the current runtime credential.\n");
        exit(1);
    }
    $previousPassword = $storedPassword;
}

$tempPasswordFile = tempnam($runtimeDir, '.db-pass-reset-');
if ($tempPasswordFile === false) {
    fwrite(STDERR, "Could not stage the runtime credential.\n");
    exit(1);
}
if (file_put_contents($tempPasswordFile, $newPassword, LOCK_EX) !== strlen($newPassword) || !chmod($tempPasswordFile, 0600)) {
    unlink($tempPasswordFile);
    fwrite(STDERR, "Could not securely stage the runtime credential.\n");
    exit(1);
}

try {
    $rootConnection = new mysqli('127.0.0.1', getenv('DB_ROOT_USER') ?: 'root', $rootPassword, 'mysql', $dbPort);
    $rootConnection->set_charset('utf8mb4');

    $userQuery = $rootConnection->prepare('SELECT Host FROM user WHERE User = ?');
    $userQuery->bind_param('s', $dbUser);
    $userQuery->execute();
    $userQuery->bind_result($userHost);
    $hosts = [];
    while ($userQuery->fetch()) {
        $hosts[] = $userHost;
    }
    $userQuery->close();

    if (!in_array('%', $hosts, true)) {
        throw new RuntimeException('The configured database account at host % was not found.');
    }
    $accountHost = '%';

    $quoteQuery = $rootConnection->prepare('SELECT QUOTE(?), QUOTE(?), QUOTE(?)');
    $quoteQuery->bind_param('sss', $dbUser, $accountHost, $newPassword);
    $quoteQuery->execute();
    $quoteQuery->bind_result($quotedUser, $quotedHost, $quotedPassword);
    if (!$quoteQuery->fetch()) {
        throw new RuntimeException('Could not prepare the database account update.');
    }
    $quoteQuery->close();

    $rootConnection->query("ALTER USER $quotedUser@$quotedHost IDENTIFIED BY $quotedPassword");

    $appConnection = new mysqli('127.0.0.1', $dbUser, $newPassword, $dbName, $dbPort);
    $appConnection->close();

    if (!rename($tempPasswordFile, $runtimePasswordFile)) {
        $quoteQuery = $rootConnection->prepare('SELECT QUOTE(?), QUOTE(?), QUOTE(?)');
        $quoteQuery->bind_param('sss', $dbUser, $accountHost, $previousPassword);
        $quoteQuery->execute();
        $quoteQuery->bind_result($quotedUser, $quotedHost, $quotedPassword);
        $quoteQuery->fetch();
        $quoteQuery->close();
        $rootConnection->query("ALTER USER $quotedUser@$quotedHost IDENTIFIED BY $quotedPassword");
        throw new RuntimeException('Could not install the runtime credential; the database password was restored.');
    }

    $rootConnection->close();
    fwrite(STDOUT, "Database password reset to the configured DB_PASS. The database contents were not changed.\n");
} catch (Exception $error) {
    if (is_file($tempPasswordFile)) {
        unlink($tempPasswordFile);
    }
    if (isset($rootConnection) && $rootConnection instanceof mysqli) {
        $rootConnection->close();
    }
    error_log('Database password recovery failed: ' . $error->getMessage());
    fwrite(STDERR, "Database password reset failed. Check the server logs and recovery configuration.\n");
    exit(1);
}
