
<?php
session_start();

require_once __DIR__ . "/conf.php";
require_once __DIR__ . "/try.php";

// =====================================================
// CONFIGURATION
// =====================================================

define('MAX_UPLOAD_BYTES', 50 * 1024 * 1024);

// CSRF protection
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

// =====================================================
// FILE TYPE GROUPS
// =====================================================

$typeGroups = [
    'presentation' => ['ppt', 'pptx', 'odp', 'key'],
    'pdf'          => ['pdf'],
    'docs'         => ['doc', 'docx', 'odt', 'rtf', 'txt'],
    'spreadsheet'  => ['xls', 'xlsx', 'ods', 'csv'],
    'video'        => ['mp4', 'mkv', 'avi', 'mov', 'webm'],
    'audio'        => ['mp3', 'wav', 'ogg', 'flac', 'm4a'],
    'image'        => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']
];

// =====================================================
// HELPER FUNCTIONS
// =====================================================

// Escape HTML output
function h($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

// Format file sizes
function formatSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $size = (float)$bytes;
    $i = 0;

    while ($size >= 1024 && $i < 3) {
        $size /= 1024;
        $i++;
    }

    return $i === 0
        ? $bytes . ' B'
        : number_format($size, 1) . ' ' . $units[$i];
}

// Get extension group
function getGroup($ext) {
    global $typeGroups;

    foreach ($typeGroups as $group => $extensions) {
        if (in_array($ext, $extensions, true)) {
            return $group;
        }
    }

    return '';
}

// Sanitize filename title
function cleanTitle($title) {
    $title = preg_replace('/[\x00-\x1F\x7F\/\\\\]/', '', (string)$title);
    $title = trim($title);
    $title = mb_substr($title, 0, 200);

    return $title === '' ? 'untitled' : $title;
}

// Check category existence
function categoryExists($pdo, $catId) {
    $stmt = $pdo->prepare(
        "SELECT cat_id FROM categories WHERE cat_id = ?"
    );

    $stmt->execute([$catId]);
    $found = $stmt->fetchColumn();
    $stmt->closeCursor();

    return $found !== false;
}

// Retrieve archive record
function getArchive($pdo, $id) {
    $stmt = $pdo->prepare(
        "SELECT * FROM archives WHERE archive_id = ?"
    );

    $stmt->execute([$id]);
    $row = $stmt->fetch();
    $stmt->closeCursor();

    return $row;
}

// Get physical file path
function diskPath($row) {
    return UPLOAD_DIR . '/' . basename($row['file_path']);
}

// =====================================================
// UPLOAD
// =====================================================

function doUpload($pdo): string {

    // Detect post_max_size overflow
    if (
        empty($_FILES) &&
        empty($_POST) &&
        (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
    ) {
        return "Upload too large. Check post_max_size and upload_max_filesize in php.ini.";
    }

    if (!isset($_FILES['files']['name'])) {
        return "No files were selected.";
    }

    $catId = (int)($_POST['cat_id'] ?? 0);

    if (!categoryExists($pdo, $catId)) {
        return "Choose a valid category first.";
    }

    // Normalize single and multiple uploads
    $names  = $_FILES['files']['name'];
    $tmps   = $_FILES['files']['tmp_name'];
    $sizes  = $_FILES['files']['size'];
    $errors = $_FILES['files']['error'];

    if (!is_array($names)) {
        $names  = [$names];
        $tmps   = [$tmps];
        $sizes  = [$sizes];
        $errors = [$errors];
    }

    $stmt = $pdo->prepare(
        "INSERT INTO archives
        (title, file_type, file_path, file_size, cat_id)
        VALUES (?, ?, ?, ?, ?)"
    );

    $saved = 0;
    $failed = 0;
    $tooLarge = 0;
    $uploadErrorCount = 0;

    foreach ($names as $i => $originalName) {

        $storedName = '';

        try {

            $uploadError = (int)($errors[$i] ?? UPLOAD_ERR_NO_FILE);
            $fileSize = (int)($sizes[$i] ?? 0);

            if ($uploadError !== UPLOAD_ERR_OK) {
                $failed++;
                $uploadErrorCount++;

                if (
                    $uploadError === UPLOAD_ERR_INI_SIZE ||
                    $uploadError === UPLOAD_ERR_FORM_SIZE
                ) {
                    $tooLarge++;
                }

                continue;
            }

            if ($fileSize > MAX_UPLOAD_BYTES) {
                $failed++;
                $tooLarge++;
                continue;
            }

            $ext = strtolower(
                pathinfo($originalName, PATHINFO_EXTENSION)
            );

            if (getGroup($ext) === '') {
                $failed++;
                continue;
            }

            // Generate a unique storage filename
            $storedName = bin2hex(random_bytes(12)) . '.dat';
            $dest = UPLOAD_DIR . '/' . $storedName;

            if (!move_uploaded_file($tmps[$i], $dest)) {
                $failed++;
                continue;
            }

            $realSize = filesize($dest) ?: $fileSize;

            $stmt->execute([
                cleanTitle(pathinfo($originalName, PATHINFO_FILENAME)),
                $ext,
                'uploads/' . $storedName,
                $realSize,
                $catId
            ]);

            $saved++;

        } catch (Throwable $e) {

            error_log("Upload error: " . $e->getMessage());

            if ($storedName !== '' && is_file(UPLOAD_DIR . '/' . $storedName)) {
                @unlink(UPLOAD_DIR . '/' . $storedName);
            }

            $failed++;
        }
    }

    $msg = "$saved file(s) added.";

    if ($tooLarge > 0) {
        $msg .= " $tooLarge file(s) rejected: maximum size is 50 MiB per file.";
    }

    if ($failed > $tooLarge) {
        $msg .= " " . ($failed - $tooLarge) . " other file(s) skipped.";
    }

    if ($uploadErrorCount > 0) {
        $msg .= " Check upload_max_filesize and post_max_size in php.ini.";
    }

    return $msg;
}

// =====================================================
// RENAME
// =====================================================

function doRename($pdo) {

    $id = (int)($_POST['id'] ?? 0);
    $title = cleanTitle($_POST['title'] ?? '');

    if ($id <= 0) {
        return "Invalid file.";
    }

    $row = getArchive($pdo, $id);

    if (!$row) {
        return "File not found.";
    }

    $stmt = $pdo->prepare(
        "UPDATE archives SET title = ? WHERE archive_id = ?"
    );

    $stmt->execute([$title, $id]);

    return $stmt->rowCount() === 0
        ? "No changes made."
        : "File renamed.";
}

// =====================================================
// RE-CATEGORIZE
// =====================================================

function doRecategorize($pdo) {

    $id = (int)($_POST['id'] ?? 0);
    $catId = (int)($_POST['cat_id'] ?? 0);

    if ($id <= 0 || !categoryExists($pdo, $catId)) {
        return "Invalid file or category.";
    }

    $stmt = $pdo->prepare(
        "UPDATE archives SET cat_id = ? WHERE archive_id = ?"
    );

    $stmt->execute([$catId, $id]);

    return "File moved to the new category.";
}

// =====================================================
// DELETE
// =====================================================

function doDelete($pdo) {

    $id = (int)($_POST['id'] ?? 0);
    $row = getArchive($pdo, $id);

    if (!$row) {
        return "File not found.";
    }

    $path = diskPath($row);

    if (is_file($path) && !unlink($path)) {
        error_log("Delete file error: $path");
        return "File could not be deleted from disk.";
    }

    $stmt = $pdo->prepare(
        "DELETE FROM archives WHERE archive_id = ?"
    );

    $stmt->execute([$id]);

    return "File deleted.";
}

// =====================================================
// ADD CATEGORY
// =====================================================

function doAddCategory($pdo) {

    $name = mb_substr(
        trim((string)($_POST['category'] ?? '')),
        0,
        30
    );

    if ($name === '') {
        return "Type a category name.";
    }

    $stmt = $pdo->prepare(
        "SELECT cat_id FROM categories WHERE category = ?"
    );

    $stmt->execute([$name]);
    $exists = $stmt->fetchColumn();
    $stmt->closeCursor();

    if ($exists !== false) {
        return "That category already exists.";
    }

    $stmt = $pdo->prepare(
        "INSERT INTO categories (category) VALUES (?)"
    );

    $stmt->execute([$name]);

    return "Category added.";
}

// =====================================================
// DOWNLOAD
// =====================================================

if (isset($_GET['download'])) {

    $handle = null;

    try {

        $row = getArchive($pdo, (int)$_GET['download']);

        if (!$row || !is_file(diskPath($row))) {
            http_response_code(404);
            die("File not found.");
        }

        $path = diskPath($row);
        $size = filesize($path);

        $niceName = $row['title'] . '.' . $row['file_type'];
        $safeName = preg_replace('/[\r\n"\\\\\/]/', '_', $niceName);

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open download file.");
        }

        $pdo = null;
        session_write_close();

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header("Content-Type: application/octet-stream");
        header(
            "Content-Disposition: attachment; filename=\"" .
            $safeName . "\"; filename*=UTF-8''" . rawurlencode($niceName)
        );
        header("Content-Length: " . $size);

        while (!feof($handle)) {
            echo fread($handle, 8192);
            flush();
        }

    } catch (Throwable $e) {

        error_log("Download error: " . $e->getMessage());

        if (!headers_sent()) {
            http_response_code(500);
            echo "The file could not be downloaded.";
        }

    } finally {

        if (is_resource($handle)) {
            fclose($handle);
        }

        $pdo = null;
    }

    exit;
}

// =====================================================
// HANDLE POST REQUESTS
// =====================================================

$queryString = $_SERVER['QUERY_STRING'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf'] ?? '';

    if (
        empty($_POST) &&
        empty($_FILES) &&
        (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
    ) {

        $_SESSION['flash'] =
            "Upload rejected: request exceeds post_max_size.";

    } elseif (!hash_equals($_SESSION['csrf'], $token)) {

        $_SESSION['flash'] = "Your session expired. Please try again.";

    } else {

        $action = $_POST['action'] ?? '';

        try {

            switch ($action) {

                case 'upload':
                    $_SESSION['flash'] = doUpload($pdo);
                    break;

                case 'rename':
                    $_SESSION['flash'] = doRename($pdo);
                    break;

                case 'recat':
                    $_SESSION['flash'] = doRecategorize($pdo);
                    break;

                case 'delete':
                    $_SESSION['flash'] = doDelete($pdo);
                    break;

                case 'addcat':
                    $_SESSION['flash'] = doAddCategory($pdo);
                    break;

                default:
                    $_SESSION['flash'] = "Invalid action.";
            }

        } catch (PDOException $e) {

            error_log("DB error: " . $e->getMessage());
            $_SESSION['flash'] = "Database error. Please try again.";

        } catch (Throwable $e) {

            error_log("Action error: " . $e->getMessage());
            $_SESSION['flash'] = "Something went wrong. Please try again.";
        }
    }

    $pdo = null;

    header(
        "Location: index.php" .
        ($queryString !== '' ? '?' . $queryString : '')
    );

    exit;
}

// =====================================================
// FLASH MESSAGE
// =====================================================

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// =====================================================
// FILTERS
// =====================================================

$cat  = (int)($_GET['cat'] ?? 0);
$mod  = $_GET['mod'] ?? '';
$type = $_GET['type'] ?? '';
$sort = $_GET['sort'] ?? 'date';
$dir  = strtoupper($_GET['dir'] ?? 'DESC');

$modSeconds = [
    'day' => 86400,
    'week' => 604800
];

// Validate filter values
if (!in_array($mod, ['day', 'week', 'month'], true)) {
    $mod = '';
}

if (!isset($typeGroups[$type])) {
    $type = '';
}

if (!in_array($sort, ['name', 'size', 'date'], true)) {
    $sort = 'date';
}

if (!in_array($dir, ['ASC', 'DESC'], true)) {
    $dir = 'DESC';
}

// =====================================================
// QUERY STRING BUILDER
// =====================================================

function makeQuery($changes) {

    global $cat, $mod, $type, $sort, $dir;

    $query = [
        'cat' => $cat,
        'mod' => $mod,
        'type' => $type,
        'sort' => $sort,
        'dir' => $dir
    ];

    foreach ($changes as $key => $value) {
        $query[$key] = $value;
    }

    return http_build_query($query);
}

// =====================================================
// LOAD AND FILTER DATABASE RECORDS
// =====================================================

$categories = [];
$rows = [];
$totalSize = 0;
$loadError = '';
$stmt = null;

try {

    // Load categories
    $stmt = $pdo->query(
        "SELECT cat_id, category FROM categories ORDER BY category"
    );

    while ($c = $stmt->fetch()) {
        $categories[] = $c;
    }

    $stmt->closeCursor();

    // Load archives
    $now = time();

    $stmt = $pdo->query(
        "SELECT
            a.archive_id,
            a.title,
            a.file_type,
            a.file_path,
            a.file_size,
            a.cat_id,
            UNIX_TIMESTAMP(a.time) AS ts,
            c.category
         FROM archives a
         JOIN categories c ON c.cat_id = a.cat_id"
    );

    // Calendar month cutoff
    $monthCutoff = strtotime('-1 month', $now);

    while ($r = $stmt->fetch()) {

        // Category filter
        if ($cat > 0 && (int)$r['cat_id'] !== $cat) {
            continue;
        }

        // Modified-time filter
        $timestamp = (int)$r['ts'];

        if ($mod === 'month' && $timestamp < $monthCutoff) {
            continue;
        }

        if (
            isset($modSeconds[$mod]) &&
            ($now - $timestamp) > $modSeconds[$mod]
        ) {
            continue;
        }

        // File type filter
        if (
            $type !== '' &&
            !in_array($r['file_type'], $typeGroups[$type], true)
        ) {
            continue;
        }

        // Normalize values
        $r['archive_id'] = (int)$r['archive_id'];
        $r['file_size'] = (int)$r['file_size'];
        $r['ts'] = $timestamp;

        $r['name_key'] = strtolower(
            $r['title'] . '.' . $r['file_type']
        );

        $totalSize += $r['file_size'];

        $rows[] = $r;
    }

    $stmt->closeCursor();
    $stmt = null;

    // Multi-column sorting
    $sortKeys = [
        'name' => 'name_key',
        'size' => 'file_size',
        'date' => 'ts'
    ];

    $rows = mergeSortMulti(
        $rows,
        [$sortKeys[$sort], 'archive_id'],
        [$dir, $dir]
    );

} catch (Throwable $e) {

    error_log("Load error: " . $e->getMessage());

    $rows = [];
    $totalSize = 0;

    $loadError =
        "The file list could not be loaded. Please refresh the page.";

} finally {

    $stmt = null;
    $pdo = null;
}

// =====================================================
// SORT HEADER BUTTON
// =====================================================

function headerButton($col, $label) {

    global $sort, $dir;

    $newDir = 'ASC';
    $class = 'hbtn';

    if ($sort === $col) {

        $class .= ' on ' . strtolower($dir);

        if ($dir === 'ASC') {
            $newDir = 'DESC';
        }
    }

    return '<a role="button" class="' . $class .
        '" href="index.php?' .
        h(makeQuery(['sort' => $col, 'dir' => $newDir])) .
        '">' . $label . '</a>';
}

?>
