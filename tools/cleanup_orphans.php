<?php
// Orphan-file cleanup (command line only).
//
//   php tools/cleanup_orphans.php            report only (safe)
//   php tools/cleanup_orphans.php --delete   actually delete the orphan files
//
// Finds:
//   1. files in uploads/ that no database row points to (and .deleting leftovers)
//   2. database rows whose file is missing on disk (reported only, never auto-deleted)
// Files younger than 1 hour are ignored, so an upload in progress is never touched.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Command line only.\n");
}

require_once __DIR__ . '/../config/conf.php';

$delete  = in_array('--delete', $argv, true);
$minAge  = 3600;
$now     = time();

$known = [];
foreach ($pdo->query("SELECT archive_id, title, file_type, file_path FROM archives") as $r) {
    $known[basename($r['file_path'])] = $r;
}

$orphans = [];
foreach (scandir(UPLOAD_DIR) as $f) {
    $full = UPLOAD_DIR . '/' . $f;
    if ($f === '.' || $f === '..' || $f === '.htaccess' || $f === 'index.html' || !is_file($full)) { continue; }
    if (isset($known[$f])) { continue; }
    if ($now - filemtime($full) < $minAge) { continue; }
    $orphans[] = $f;
}

$missing = [];
foreach ($known as $name => $r) {
    if (!is_file(UPLOAD_DIR . '/' . $name)) { $missing[] = $r; }
}

echo "Orphan files on disk (no database row): " . count($orphans) . "\n";
foreach ($orphans as $f) {
    $size = filesize(UPLOAD_DIR . '/' . $f);
    if ($delete && @unlink(UPLOAD_DIR . '/' . $f)) {
        echo "  deleted  $f ($size bytes)\n";
    } else {
        echo "  orphan   $f ($size bytes)\n";
    }
}

echo "Database rows with a missing file: " . count($missing) . "\n";
foreach ($missing as $r) {
    echo "  #{$r['archive_id']}  {$r['title']}.{$r['file_type']}  ->  {$r['file_path']}\n";
}

if (!$delete && $orphans) {
    echo "\nRun again with --delete to remove the orphan files.\n";
}
