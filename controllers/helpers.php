<?php
// Data helpers shared by the controllers

function inputStr(array $source, string $key, string $default = ''): string {
    $value = $source[$key] ?? $default;
    return is_string($value) ? $value : $default;
}

// Read a request value as an integer (0 when missing or not a plain string)
function inputInt(array $source, string $key): int {
    $value = $source[$key] ?? 0;
    return is_string($value) ? (int)$value : 0;
}

// Upload limit in MiB, for messages
function maxUploadMiB(): int {
    return (int)(MAX_UPLOAD_BYTES / 1048576);
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
    // Replace invalid UTF-8 (otherwise MySQL rejects the row)
    $title = mb_scrub((string)$title);

   
    $title = preg_replace(
        '/[\x00-\x1F\x7F\/\\\\\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u',
        '',
        $title
    ) ?? '';

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
