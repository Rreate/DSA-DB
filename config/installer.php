<?php
// Install functions, shared by install.php (manual form) and config/conf.php
// (automatic first-run setup on localhost). Not reachable from the web.

const DENY_RULES = "# No direct web access to this folder.\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n";

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function ident($s) { return (bool)preg_match('/^[A-Za-z0-9_]{1,32}$/', $s); }

function columnExists(PDO $pdo, string $db, string $table, string $col): bool {
    $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?");
    $s->execute([$db, $table, $col]);
    return (int)$s->fetchColumn() > 0;
}
function indexExists(PDO $pdo, string $db, string $table, string $idx): bool {
    $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=?");
    $s->execute([$db, $table, $idx]);
    return (int)$s->fetchColumn() > 0;
}

// Upgrade a database created by the OLD version of the app (safe to repeat)
function migrate(PDO $pdo, string $db, array &$log): void {
    if (!columnExists($pdo, $db, 'archives', 'user_id')) {
        $pdo->exec("ALTER TABLE `archives` ADD COLUMN `user_id` BIGINT UNSIGNED NULL AFTER `cat_id`");
        $log[] = "Added archives.user_id";
    }
    $len = (int)$pdo->query("SELECT COALESCE(MAX(CHAR_LENGTH(file_type)),0) FROM archives")->fetchColumn();
    $s = $pdo->prepare("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='archives' AND COLUMN_NAME='file_type'");
    $s->execute([$db]);
    if ((int)$s->fetchColumn() > 10 && $len <= 10) {
        $pdo->exec("ALTER TABLE `archives` MODIFY `file_type` VARCHAR(10) NOT NULL");
        $log[] = "Shrunk archives.file_type to VARCHAR(10)";
    }
    $indexes = [
        'idx_archives_user_time' => '(`user_id`, `time`)',
        'idx_archives_user_type' => '(`user_id`, `file_type`)',
        'idx_archives_cat_time'  => '(`cat_id`, `time`)',
    ];
    foreach ($indexes as $name => $cols) {
        if (!indexExists($pdo, $db, 'archives', $name)) {
            $pdo->exec("ALTER TABLE `archives` ADD KEY `$name` $cols");
            $log[] = "Added index $name";
        }
    }
    // Old schema used ON DELETE CASCADE (leaves orphan files). Switch to RESTRICT.
    $s = $pdo->prepare("SELECT CONSTRAINT_NAME, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
                        WHERE CONSTRAINT_SCHEMA=? AND TABLE_NAME='archives' AND REFERENCED_TABLE_NAME='categories'");
    $s->execute([$db]);
    foreach ($s->fetchAll() as $fk) {
        if ($fk['DELETE_RULE'] === 'CASCADE') {
            $pdo->exec("ALTER TABLE `archives` DROP FOREIGN KEY `{$fk['CONSTRAINT_NAME']}`");
            $pdo->exec("ALTER TABLE `archives` ADD CONSTRAINT `fk_archives_cat` FOREIGN KEY (`cat_id`) REFERENCES `categories`(`cat_id`) ON DELETE RESTRICT ON UPDATE CASCADE");
            $log[] = "Changed category foreign key from CASCADE to RESTRICT";
        }
    }
    $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=? AND TABLE_NAME='archives' AND REFERENCED_TABLE_NAME='users'");
    $s->execute([$db]);
    if ((int)$s->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `archives` ADD CONSTRAINT `fk_archives_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE");
        $log[] = "Added foreign key archives.user_id -> users";
    }
}

function runInstall(array $in, string $localFile, string $lockFile): array {
    $denyRules = DENY_RULES;
    $log = [];
    $dbHost = trim($in['db_host']);
    $dbName = trim($in['db_name']);
    $appUser = trim($in['app_user']);

    if ($dbHost === '' || !ident($dbName) || !ident($appUser)) {
        throw new RuntimeException("Database name and app user may only use letters, digits and _ (max 32).");
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{1,50}$/', $in['admin_name'])) {
        throw new RuntimeException("Login name: 1-50 letters, digits, . _ -");
    }
    if (strlen($in['admin_pass']) < 8) {
        throw new RuntimeException("The app password must be at least 8 characters.");
    }

    // 1. Connect with the ADMIN account (used only inside this request)
    $root = new PDO("mysql:host=$dbHost;charset=utf8mb4", $in['root_user'], $in['root_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    $root->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $root->exec("USE `$dbName`");
    $log[] = "Database `$dbName` ready";

    // 2. Tables, then upgrade an old database if there is one
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    if ($sql === false) { throw new RuntimeException("Cannot read config/schema.sql"); }
    $root->exec($sql);
    $log[] = "Tables created";
    migrate($root, $dbName, $log);

    // 3. Limited MySQL user for the running app
    $appPass = $in['app_pass'] !== '' ? $in['app_pass'] : bin2hex(random_bytes(16));
    $grantHost = in_array($dbHost, ['localhost', '127.0.0.1', '::1'], true) ? 'localhost' : '%';
    $u = $root->quote($appUser);
    $h = $root->quote($grantHost);
    $root->exec("CREATE USER IF NOT EXISTS $u@$h IDENTIFIED BY " . $root->quote($appPass));
    $root->exec("ALTER USER $u@$h IDENTIFIED BY " . $root->quote($appPass));
    $root->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON `$dbName`.* TO $u@$h");
    $root->exec("FLUSH PRIVILEGES");
    $log[] = "Limited MySQL user `$appUser` created (SELECT, INSERT, UPDATE, DELETE only)";

    // 4. Default category + first login
    if ((int)$root->query("SELECT COUNT(*) FROM categories")->fetchColumn() === 0) {
        $root->exec("INSERT INTO categories (category) VALUES ('General')");
    }
    $hash = password_hash($in['admin_pass'], PASSWORD_DEFAULT);
    $st = $root->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)
                          ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)");
    $st->execute([$in['admin_name'], $hash]);
    $uid = (int)$root->query("SELECT user_id FROM users WHERE username = " . $root->quote($in['admin_name']))->fetchColumn();
    $claimed = $root->exec("UPDATE archives SET user_id = $uid WHERE user_id IS NULL");
    $log[] = "Login `{$in['admin_name']}` created" . ($claimed ? " and given $claimed existing file(s)" : "");

    // 5. Uploads folder + deny-all rules
    $upload = dirname(__DIR__) . '/uploads';
    if (!is_dir($upload) && !mkdir($upload, 0755, true)) { throw new RuntimeException("Cannot create uploads/"); }
    file_put_contents($upload . '/.htaccess', $denyRules);
    $log[] = "uploads/ protected from direct web access";

    // 6. Save credentials (file is also denied to the web) and lock the installer
    $conf = "<?php\n// Written by install.php. Keep private.\nreturn " . var_export([
        'host' => $dbHost, 'db' => $dbName, 'user' => $appUser, 'password' => $appPass,
    ], true) . ";\n";
    if (file_put_contents($localFile, $conf, LOCK_EX) === false) { throw new RuntimeException("Cannot write config/local.php"); }
    @chmod($localFile, 0640);
    file_put_contents($lockFile, date('c') . "\n");
    $log[] = "config/local.php written, installer locked";
    return $log;
}

