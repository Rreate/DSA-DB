<?php
// Flash message, filters, pagination, and loading/sorting the file list

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);


// FILTERS (every value is forced to a plain string/int first)

$cat  = inputInt($_GET, 'cat');
$mod  = inputStr($_GET, 'mod');
$type = inputStr($_GET, 'type');
$sort = inputStr($_GET, 'sort', 'date');
$dir  = strtoupper(inputStr($_GET, 'dir', 'DESC'));
$page = max(1, inputInt($_GET, 'page'));

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
// "page" is left out unless a link asks for it, so changing a filter or the
// sort order always starts again from page 1.

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
$totalFiles = 0;     // files matching the filters (all pages)
$totalSize = 0;      // their combined size
$pages = 1;
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

    // Filters are applied in SQL. Users only ever see their own files.
    $now    = time();
    $where  = ['a.user_id = ?'];
    $params = [$userId];

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

    $whereSql = ' WHERE ' . implode(' AND ', $where);

    // STEP 1: a LIGHT query - only the 4 columns needed to sort (no joins, no paths).
    $stmt = $pdo->prepare(
        "SELECT a.archive_id, a.title, a.file_type, a.file_size,
                UNIX_TIMESTAMP(a.time) AS ts
         FROM archives a" . $whereSql
    );
    $stmt->execute($params);

    $light = [];
    while ($r = $stmt->fetch()) {
        $r['archive_id'] = (int)$r['archive_id'];
        $r['file_size']  = (int)$r['file_size'];
        $r['ts']         = (int)$r['ts'];
        $r['name_key']   = mb_strtolower($r['title'] . '.' . $r['file_type']);

        $totalSize += $r['file_size'];
        $light[] = $r;
    }

    $stmt->closeCursor();
    $stmt = null;

    $totalFiles = count($light);

    // STEP 2: sort with our own merge sort (see dsa/merge_sort.php).
    // It gives natural order (file2 before file10), which MySQL ORDER BY can't.
    $sortKeys = [
        'name' => 'name_key',
        'size' => 'file_size',
        'date' => 'ts'
    ];

    $light = mergeSortMulti(
        $light,
        [$sortKeys[$sort], 'archive_id'],
        [$dir, $dir]
    );

    // STEP 3: cut out just the current page
    $pages = max(1, (int)ceil($totalFiles / PER_PAGE));
    $page  = min($page, $pages);
    $ids   = array_column(array_slice($light, ($page - 1) * PER_PAGE, PER_PAGE), 'archive_id');
    $light = null;   // free the memory

    // STEP 4: load the full rows for only those few ids, in the sorted order
    if ($ids) {
        $in   = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "SELECT a.archive_id, a.title, a.file_type, a.file_path, a.file_size,
                    a.cat_id, UNIX_TIMESTAMP(a.time) AS ts, c.category
             FROM archives a
             JOIN categories c ON c.cat_id = a.cat_id
             WHERE a.user_id = ? AND a.archive_id IN ($in)"
        );
        $stmt->execute(array_merge([$userId], $ids));

        $byId = [];
        while ($r = $stmt->fetch()) {
            $r['archive_id'] = (int)$r['archive_id'];
            $r['file_size']  = (int)$r['file_size'];
            $r['ts']         = (int)$r['ts'];
            $byId[$r['archive_id']] = $r;
        }
        $stmt->closeCursor();
        $stmt = null;

        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $rows[] = $byId[$id];
            }
        }
    }

} catch (Throwable $e) {

    error_log("Load error: " . $e->getMessage());

    $rows = [];
    $totalFiles = 0;
    $totalSize = 0;

    $loadError =
        "The file list could not be loaded. Please refresh the page.";

} finally {

    $stmt = null;
    $pdo = null;
}
