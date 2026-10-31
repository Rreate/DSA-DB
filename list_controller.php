<?php
// Flash message, filters, query builder, and loading/sorting the file list

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);


// FILTERS


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


// QUERY STRING BUILDER


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


// LOAD AND FILTER DATABASE RECORDS


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
