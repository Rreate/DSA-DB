<?php
// Runtime configuration + database connection.
//
// This file no longer creates databases or tables: that is install.php's job,
// done once with an admin account. The app connects with a LIMITED account
// (SELECT / INSERT / UPDATE / DELETE only). Credentials come from
// environment variables or from config/local.php (written by install.php).

$cfg = [];
$localFile = __DIR__ . '/local.php';
if (is_file($localFile)) {
    $loaded = require $localFile;
    if (is_array($loaded)) {
        $cfg = $loaded;
    }
}

function cfgValue(array $cfg, string $env, string $key, $default = '') {
    $fromEnv = getenv($env);
    if ($fromEnv !== false) {
        return $fromEnv;
    }
    return $cfg[$key] ?? $default;
}

$host     = cfgValue($cfg, 'ARCHIVE_DB_HOST', 'host', 'localhost');
$db       = cfgValue($cfg, 'ARCHIVE_DB_NAME', 'db', 'archive');
$user     = cfgValue($cfg, 'ARCHIVE_DB_USER', 'user', '');
$password = cfgValue($cfg, 'ARCHIVE_DB_PASS', 'password', '');

// Uploaded files live here. Point it OUTSIDE the web root if you can
// (set "upload_dir" in local.php or ARCHIVE_UPLOAD_DIR).
define('UPLOAD_DIR', rtrim((string)cfgValue($cfg, 'ARCHIVE_UPLOAD_DIR', 'upload_dir', dirname(__DIR__) . '/uploads'), '/\\'));

// Not installed yet.
if ($user === '') {

    // QUICK START: when you open the site from THIS computer (localhost) and MySQL
    // still has XAMPP's default admin (root, no password), set everything up
    // automatically with the values below, then reload. Nothing to click.
    // On any other machine, or if MySQL has a root password, install.php is used instead.
    $fromThisPc = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    if (PHP_SAPI !== 'cli' && $fromThisPc && !is_file(__DIR__ . '/install.lock')) {
        try {
            require_once __DIR__ . '/installer.php';
            runInstall([
                'db_host'    => 'localhost',
                'root_user'  => 'root',
                'root_pass'  => '',
                'db_name'    => 'archive',
                'app_user'   => 'archive_app',
                'app_pass'   => '',               // empty = random password
                'admin_name' => 'admin',          // first website login
                'admin_pass' => 'John-Netherite', // change it with tools/set_password.php
            ], __DIR__ . '/local.php', __DIR__ . '/install.lock');
            header('Location: ' . ($_SERVER['REQUEST_URI'] ?? 'index.php'));
            exit;
        } catch (Throwable $e) {
            error_log("Quick start failed: " . $e->getMessage());   // fall through to install.php
        }
    }

    if (PHP_SAPI === 'cli') {
        die("Not installed. Run install.php first.\n");
    }
    if (is_file(dirname(__DIR__) . '/install.php')) {
        header('Location: install.php');
        exit;
    }
    http_response_code(500);
    die("The application is not configured. Create config/local.php or set the ARCHIVE_DB_* variables.");
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Make the upload folder if it does not exist yet
    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) {
        throw new RuntimeException("Cannot create the uploads folder");
    }
    if (!is_writable(UPLOAD_DIR)) {
        throw new RuntimeException("The uploads folder is not writable");
    }
} catch (PDOException $e) {
    error_log("DB Error: " . $e->getMessage());
    $pdo = null;
    http_response_code(500);
    die("The database is not available. Check that MySQL is running and the settings in config/local.php are right.");
} catch (Throwable $e) {
    error_log("Setup Error: " . $e->getMessage());
    $pdo = null;
    http_response_code(500);
    die("The system could not start. Check the server log for details.");
}
