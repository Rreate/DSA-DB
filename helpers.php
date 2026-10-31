<?php
// Data helpers shared by the controllers

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
