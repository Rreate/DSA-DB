<?php
// Change a login's password (command line only).
//
//   php tools/set_password.php admin                 asks for the new password
//   php tools/set_password.php admin "NewPass123"    sets it directly
//
// Also clears any lockout on that account.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Command line only.\n");
}

require_once __DIR__ . '/../config/conf.php';
require_once __DIR__ . '/../config/byted.php';

$name = $argv[1] ?? '';
if ($name === '') {
    exit("Usage: php tools/set_password.php <username> [new-password]\n");
}

$pass = $argv[2] ?? '';
if ($pass === '') {
    echo "New password for $name: ";
    $pass = rtrim((string)fgets(STDIN), "\r\n");
}

if (strlen($pass) < MIN_PASSWORD_LENGTH) {
    exit("Password must be at least " . MIN_PASSWORD_LENGTH . " characters.\n");
}

$stmt = $pdo->prepare(
    "UPDATE users SET password_hash = ?, failed_logins = 0, locked_until = NULL WHERE username = ?"
);
$stmt->execute([password_hash($pass, PASSWORD_DEFAULT), $name]);

echo $stmt->rowCount() > 0
    ? "Password changed for $name.\n"
    : "No user named '$name' (or the password was already that value).\n";
