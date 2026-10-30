<?php
session_start();
require_once __DIR__ . "/conf.php";
require_once __DIR__ . "/try.php";   // mergeSortMulti() and compareMulti()

// Maximum size per file: 50 MiB (50 * 1024 * 1024 bytes).
define('MAX_UPLOAD_BYTES', 50 * 1024 * 1024);

// Token that protects every form from forged requests
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

/* =====================================================
   SMALL HELPER FUNCTIONS
   ===================================================== */

// Make text safe to print inside HTML
function h($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

// 1536 -> "1.5 KB"
function formatSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $size = (float)$bytes;
    while ($size >= 1024 && $i < 3) {
        $size = $size / 1024;
        $i = $i + 1;
    }
    if ($i == 0) {
        return $bytes . " B";
    }
    return number_format($size, 1) . " " . $units[$i];
}

// File type groups used by the "Type" dropdown
$typeGroups = [
    'presentation' => ['ppt', 'pptx', 'odp', 'key'],
    'pdf'          => ['pdf'],
    'docs'         => ['doc', 'docx', 'odt', 'rtf', 'txt'],
    'spreadsheet'  => ['xls', 'xlsx', 'ods', 'csv'],
    'video'        => ['mp4', 'mkv', 'avi', 'mov', 'webm'],
    'audio'        => ['mp3', 'wav', 'ogg', 'flac', 'm4a'],
    'image'        => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']
];

// Return the group name of an extension, or '' if not allowed
function getGroup($ext) {
    global $typeGroups;
    foreach ($typeGroups as $group => $list) {
        if (in_array($ext, $list, true)) {
            return $group;
        }
    }
    return '';
}

// Clean a file name typed by the user
function cleanTitle($title) {
    $title = preg_replace('/[\x00-\x1F\x7F\/\\\\]/', '', (string)$title);
    $title = trim($title);
    $title = mb_substr($title, 0, 200);
    if ($title === '') {
        $title = 'untitled';
    }
    return $title;
}

function categoryExists($pdo, $catId) {
    $stmt = $pdo->prepare("SELECT cat_id FROM categories WHERE cat_id = ?");
    $stmt->execute([$catId]);
    $found = $stmt->fetch();
    $stmt->closeCursor();
    return $found !== false;
}

function getArchive($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM archives WHERE archive_id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    $stmt->closeCursor();
    return $row;
}

// Full path of the stored file on the server disk
function diskPath($row) {
    return UPLOAD_DIR . "/" . basename($row['file_path']);
}

/* =====================================================
   ACTIONS (upload, rename, re-categorize, delete, ...)
   Each returns a message to show the user.
   ===================================================== */

function doUpload($pdo) {
    $catId = (int)($_POST['cat_id'] ?? 0);
    if (!categoryExists($pdo, $catId)) {
        return "Choose a category first.";
    }
    if (!isset($_FILES['files']) || !isset($_FILES['files']['name'])) {
        return "No files were selected. If the request was too large, check post_max_size in php.ini.";
    }

    $names  = $_FILES['files']['name'];
    $tmps   = $_FILES['files']['tmp_name'];
    $sizes  = $_FILES['files']['size'];
    $errors = $_FILES['files']['error'];

    $stmt = $pdo->prepare(
        "INSERT INTO archives (title, file_type, file_path, file_size, cat_id) VALUES (?, ?, ?, ?, ?)"
    );

    $saved = 0;
    $failed = 0;
    $tooLarge = 0;
    $uploadErrorCount = 0;

    for ($i = 0; $i < count($names); $i++) {
        $storedName = '';
        try {
            $originalName = (string)$names[$i];
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $uploadError = (int)($errors[$i] ?? UPLOAD_ERR_NO_FILE);
            // This is PHP's uploaded-file size metadata, not a client-provided form field.
            $fileSize = (int)($sizes[$i] ?? 0);

            if ($uploadError !== UPLOAD_ERR_OK) {
                $failed++;
                $uploadErrorCount++;
                if ($uploadError === UPLOAD_ERR_INI_SIZE ||
                    $uploadError === UPLOAD_ERR_FORM_SIZE) {
                    $tooLarge++;
                }
                continue;
            }

            // Reject files over 50 MiB before moving or saving a database record.
            if ($fileSize > MAX_UPLOAD_BYTES) {
                $failed++;
                $tooLarge++;
                continue;
            }

            if (getGroup($ext) === '') {
                $failed++;
                continue;
            }

            // Stored under a random name with .dat so the server never runs it.
            $storedName = bin2hex(random_bytes(12)) . ".dat";
            if (!move_uploaded_file($tmps[$i], UPLOAD_DIR . "/" . $storedName)) {
                $storedName = '';
                $failed++;
                continue;
            }

            $stmt->execute([
                cleanTitle(pathinfo($originalName, PATHINFO_FILENAME)),
                $ext,
                "uploads/" . $storedName,
                $fileSize,
                $catId
            ]);
            $saved++;
        } catch (Throwable $e) {
            error_log("Upload error: " . $e->getMessage());
            if ($storedName !== '' && is_file(UPLOAD_DIR . "/" . $storedName)) {
                @unlink(UPLOAD_DIR . "/" . $storedName);
            }
            $failed++;
        }
    }

    $stmt = null;
    $msg = $saved . " file(s) added.";
    if ($tooLarge > 0) {
        $msg .= " " . $tooLarge . " file(s) rejected: maximum size is 50 MiB per file.";
    }
    if ($failed > $tooLarge) {
        $msg .= " " . ($failed - $tooLarge) . " other file(s) skipped (unsupported type or upload error).";
    }
    if ($uploadErrorCount > 0) {
        $msg .= " Check upload_max_filesize and post_max_size in php.ini if the server rejected a large upload.";
    }
    return $msg;
}

function doRename($pdo) {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE archives SET title = ? WHERE archive_id = ?");
    $stmt->execute([cleanTitle($_POST['title'] ?? ''), $id]);
    return "File renamed.";
}

function doRecategorize($pdo) {
    $id = (int)($_POST['id'] ?? 0);
    $catId = (int)($_POST['cat_id'] ?? 0);
    if (!categoryExists($pdo, $catId)) {
        return "That category does not exist.";
    }
    $stmt = $pdo->prepare("UPDATE archives SET cat_id = ? WHERE archive_id = ?");
    $stmt->execute([$catId, $id]);
    return "File moved to the new category.";
}

function doDelete($pdo) {
    $id = (int)($_POST['id'] ?? 0);
    $row = getArchive($pdo, $id);
    if (!$row) {
        return "File not found.";
    }
    $stmt = $pdo->prepare("DELETE FROM archives WHERE archive_id = ?");
    $stmt->execute([$id]);
    try {
        if (is_file(diskPath($row))) {
            unlink(diskPath($row));
        }
    } catch (Throwable $e) {
        error_log("Delete file error: " . $e->getMessage());
        return "The record was removed, but the file could not be deleted from the server disk.";
    }
    return "File deleted.";
}

function doAddCategory($pdo) {
    $name = trim((string)($_POST['category'] ?? ''));
    $name = mb_substr($name, 0, 30);
    if ($name === '') {
        return "Type a category name.";
    }
    $stmt = $pdo->prepare("SELECT cat_id FROM categories WHERE category = ?");
    $stmt->execute([$name]);
    $exists = $stmt->fetch();
    $stmt->closeCursor();
    if ($exists) {
        return "That category already exists.";
    }
    $stmt = $pdo->prepare("INSERT INTO categories (category) VALUES (?)");
    $stmt->execute([$name]);
    return "Category added.";
}

/* =====================================================
   DOWNLOAD  (sent in small pieces so memory stays low)
   ===================================================== */
if (isset($_GET['download'])) {
    $handle = null;
    try {
        $row = getArchive($pdo, (int)$_GET['download']);
        if (!$row || !is_file(diskPath($row))) {
            http_response_code(404);
            $pdo = null;
            die("File not found.");
        }
        $path = diskPath($row);
        $size = filesize($path);
        $niceName = $row['title'] . "." . $row['file_type'];
        $safeName = preg_replace('/[\r\n"\\\\\/]/', '_', $niceName);

        $handle = fopen($path, "rb");   // open before sending headers, so a failure can still show an error

        $pdo = null;               // database is not needed any more
        session_write_close();     // release the session lock
        while (ob_get_level() > 0) {
            ob_end_clean();        // no output buffer holding the file in memory
        }

        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"" . $safeName . "\"; filename*=UTF-8''" . rawurlencode($niceName));
        header("Content-Length: " . $size);

        while (!feof($handle)) {
            echo fread($handle, 8192);   // 8 KB at a time
            flush();
        }
    } catch (Throwable $e) {
        error_log("Download error: " . $e->getMessage());
        if (!headers_sent()) {
            http_response_code(500);
            echo "The file could not be downloaded. Please try again.";
        }
    } finally {
        if (is_resource($handle)) {
            fclose($handle);             // always close the file
        }
        $pdo = null;
    }
    exit;
}

/* =====================================================
   HANDLE FORM POSTS, then redirect (so refresh is safe)
   ===================================================== */
$queryString = $_SERVER['QUERY_STRING'] ?? '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $token = $_POST['csrf'] ?? '';
    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        // PHP drops the entire POST body when it exceeds post_max_size.
        $_SESSION['flash'] = "Upload rejected: request exceeds post_max_size. Set post_max_size above 50M in php.ini, with extra room for multiple files and form data.";
    } elseif (!hash_equals($_SESSION['csrf'], $token)) {
        $_SESSION['flash'] = "Your session expired. Please try again.";
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'upload')        { $_SESSION['flash'] = doUpload($pdo); }
            elseif ($action === 'rename')    { $_SESSION['flash'] = doRename($pdo); }
            elseif ($action === 'recat')     { $_SESSION['flash'] = doRecategorize($pdo); }
            elseif ($action === 'delete')    { $_SESSION['flash'] = doDelete($pdo); }
            elseif ($action === 'addcat')    { $_SESSION['flash'] = doAddCategory($pdo); }
        } catch (PDOException $e) {
            error_log("DB error in action: " . $e->getMessage());
            $_SESSION['flash'] = "Database error. Nothing was changed.";
        } catch (Throwable $e) {
            error_log("Action error: " . $e->getMessage());
            $_SESSION['flash'] = "Something went wrong. Please try again.";
        }
    }
    $pdo = null;
    header("Location: index.php" . ($queryString !== '' ? "?" . $queryString : ""));
    exit;
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

/* =====================================================
   READ THE FILTERS FROM THE DROPDOWNS (GET)
   ===================================================== */
$cat  = (int)($_GET['cat'] ?? 0);                 // 0 = all categories
$mod  = $_GET['mod'] ?? '';                       // day | week | month | ''
$type = $_GET['type'] ?? '';                      // a key of $typeGroups | ''
$sort = $_GET['sort'] ?? 'date';                  // id | name | size | date
$dir  = strtoupper($_GET['dir'] ?? 'DESC');       // ASC | DESC

$modSeconds = ['day' => 86400, 'week' => 604800, 'month' => 2592000];
if (!isset($modSeconds[$mod]))      { $mod = ''; }
if (!isset($typeGroups[$type]))     { $type = ''; }
if (!in_array($sort, ['name', 'size', 'date'], true)) { $sort = 'date'; }
if ($dir !== 'ASC' && $dir !== 'DESC') { $dir = 'DESC'; }

// Build a query string for links, keeping the current filters
function makeQuery($changes) {
    global $cat, $mod, $type, $sort, $dir;
    $q = ['cat' => $cat, 'mod' => $mod, 'type' => $type, 'sort' => $sort, 'dir' => $dir];
    foreach ($changes as $k => $v) {
        $q[$k] = $v;
    }
    return http_build_query($q);
}

/* =====================================================
   LOAD SQL DATA INTO AN ARRAY, FILTER IT, THEN MERGE SORT
   ===================================================== */
$categories = [];
$rows = [];
$totalSize = 0;
$loadError = '';
$stmt = null;

try {
    $stmt = $pdo->query("SELECT cat_id, category FROM categories ORDER BY category");
    while ($c = $stmt->fetch()) {
        $categories[] = $c;
    }
    $stmt->closeCursor();

    $now = time();
    $stmt = $pdo->query(
        "SELECT a.archive_id, a.title, a.file_type, a.file_path, a.file_size, a.cat_id,
                UNIX_TIMESTAMP(a.time) AS ts, c.category
         FROM archives a JOIN categories c ON c.cat_id = a.cat_id"
    );
    // Row by row: rows that fail a filter are never kept in memory
    while ($r = $stmt->fetch()) {
        if ($cat > 0 && (int)$r['cat_id'] !== $cat) { continue; }
        if ($mod !== '' && ($now - (int)$r['ts']) > $modSeconds[$mod]) { continue; }
        if ($type !== '' && !in_array($r['file_type'], $typeGroups[$type], true)) { continue; }

        $r['archive_id'] = (int)$r['archive_id'];
        $r['file_size']  = (int)$r['file_size'];
        $r['ts']         = (int)$r['ts'];
        $r['name_key']   = strtolower($r['title'] . "." . $r['file_type']);
        $totalSize       = $totalSize + $r['file_size'];
        $rows[] = $r;
    }
    $stmt->closeCursor();
    $stmt = null;

    // Which array key does each header button sort by?
    $sortKeys = ['name' => 'name_key', 'size' => 'file_size', 'date' => 'ts'];

    // archive_id is the second key, so equal values always keep a steady order
    $rows = mergeSortMulti($rows, [$sortKeys[$sort], 'archive_id'], [$dir, $dir]);
} catch (Throwable $e) {
    // Show an empty list and a clear message instead of a broken page
    error_log("Load error: " . $e->getMessage());
    $rows = [];
    $totalSize = 0;
    $loadError = "The file list could not be loaded. Please refresh the page.";
} finally {
    $stmt = null;
    $pdo = null;   // finished with the database, even after an error
}

// One header button: clicking again flips the direction
function headerButton($col, $label) {
    global $sort, $dir;
    $newDir = 'ASC';
    $class = 'hbtn';
    if ($sort === $col) {
        $class = $class . ' on ' . strtolower($dir);
        if ($dir === 'ASC') { $newDir = 'DESC'; }
    }
    return '<a role="button" class="' . $class . '" href="index.php?' . h(makeQuery(['sort' => $col, 'dir' => $newDir])) . '">' . $label . '</a>';
}
?>