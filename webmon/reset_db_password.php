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

if ($dbUser === '' || $dbName === '' || $newPassword === false || $newPassword === '' || $rootPassword === false || $rootPassword === '') {
    fwrite(STDERR, "Required database recovery configuration is missing.\n");
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
    $rootConnection->close();

    fwrite(STDOUT, "Database password synchronized with .env DB_PASS. The database contents were not changed.\n");
} catch (Exception $error) {
    if (isset($rootConnection) && $rootConnection instanceof mysqli) {
        $rootConnection->close();
    }
    error_log('Database password sync failed: ' . $error->getMessage());
    fwrite(STDERR, "Database password sync failed. Check the server logs and .env values.\n");
    exit(1);
}
