<?php
session_start();

// Config
require_once __DIR__ . '/config/conf.php';   // error policy, PDO ($pdo), upload folder
require_once __DIR__ . '/config/byted.php';    // MAX_UPLOAD_BYTES, $typeGroups

// Data structures & algorithms
require_once __DIR__ . '/dsa/merge_sort.php';

// Helpers
require_once __DIR__ . '/controllers/helpers.php';
require_once __DIR__ . '/views/helpers.php';

// CSRF protection
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

// Controllers
require_once __DIR__ . '/controllers/archive_controller.php';
require_once __DIR__ . '/controllers/category_controller.php';
require_once __DIR__ . '/controllers/download_controller.php';   // exits if ?download=
require_once __DIR__ . '/controllers/post_controller.php';       // exits after any POST
require_once __DIR__ . '/controllers/list_controller.php';       // GET: filters + data

// View
require __DIR__ . '/views/layout.php';
