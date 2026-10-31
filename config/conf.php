<?php

// ---------- Settings ----------
$host     = "localhost";
$user     = "root";
$password = "";
$db       = "archive";
$sqlFile  = __DIR__ . "/schema.sql";

// Folder where uploaded files are stored, and the biggest file allowed (50 MB)
define('UPLOAD_DIR', dirname(__DIR__) . "/uploads");

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$db`");

    // Create the tables from schema.sql (safe to repeat: IF NOT EXISTS)
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new RuntimeException("Cannot read schema.sql");
    }
    $pdo->exec($sql);
    $sql = null; // free the text

    // Make the upload folder if it does not exist yet
    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) {
        throw new RuntimeException("Cannot create the uploads folder");
    }
    if (!is_writable(UPLOAD_DIR)) {
        throw new RuntimeException("The uploads folder is not writable");
    }

    // Make sure there is at least one category
    $check = $pdo->query("SELECT COUNT(*) FROM categories");
    $count = (int)$check->fetchColumn();
    $check->closeCursor();
    $check = null;
    if ($count === 0) {
        $pdo->exec("INSERT INTO categories (category) VALUES ('General')");
    }
} catch (PDOException $e) {
    error_log("DB Error: " . $e->getMessage());
    $pdo = null;
    http_response_code(500);
    die("The database is not available. Check that MySQL is running and the settings in conf.php are right.");
} catch (Throwable $e) {
    error_log("Setup Error: " . $e->getMessage());
    $pdo = null;
    http_response_code(500);
    die("The system could not start. Check the server log for details.");
}
