<?php
// Flash message, filters, query builder, and loading/sorting the file list

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);


// FILTERS (every value is forced to a plain string/int first)

$cat  = inputInt($_GET, 'cat');
$mod  = inputStr($_GET, 'mod');
$type = inputStr($_GET, 'type');
$sort = inputStr($_GET, 'sort', 'date');
$dir  = strtoupper(inputStr($_GET, 'dir', 'DESC'));

$modSeconds = [
    'day'  => 86400,
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

    // Filters are applied in SQL, so only matching rows are ever loaded
    $now    = time();
    $where  = [];
    $params = [];

    if ($cat > 0) {
        $where[]  = 'a.cat_id = ?';
        $params[] = $cat;
    }

    if ($mod !== '') {
        $cutoff = ($mod === 'month')
            ? strtotime('-1 month', $now)
            : $now - $modSeconds[$mod];

        $where[]  = 'a.time >= FROM_UNIXTIME(CAST(? AS UNSIGNED))';
        $params[] = $cutoff;
    }

    if ($type !== '') {
        $exts     = $typeGroups[$type];
        $where[]  = 'a.file_type IN (' . implode(',', array_fill(0, count($exts), '?')) . ')';
        foreach ($exts as $ext) {
            $params[] = $ext;
        }
    }

    $sql = "SELECT
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
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '');

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    while ($r = $stmt->fetch()) {

        // Normalize values
        $r['archive_id'] = (int)$r['archive_id'];
        $r['file_size']  = (int)$r['file_size'];
        $r['ts']         = (int)$r['ts'];

        $r['name_key'] = mb_strtolower(
            $r['title'] . '.' . $r['file_type']
        );

        $totalSize += $r['file_size'];

        $rows[] = $r;
    }

    $stmt->closeCursor();
    $stmt = null;

    // Multi-column sorting (our own merge sort, see dsa/merge_sort.php)
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
