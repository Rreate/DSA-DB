<?php
// Actions on categories

function doAddCategory($pdo) {

    $name = mb_scrub(inputStr($_POST, 'category'));
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
    $name = mb_substr(trim($name), 0, 30);

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

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO categories (category) VALUES (?)"
        );

        $stmt->execute([$name]);
    } catch (PDOException $e) {
        // 23000 = duplicate key: two requests added the same name at once
        if ($e->getCode() === '23000') {
            return "That category already exists.";
        }
        throw $e;
    }

    return "Category added.";
}


// DELETE CATEGORY
// Only an EMPTY category can be deleted, so no file is ever left on disk
// without a database row (the foreign key is RESTRICT as well).

function doDeleteCategory($pdo) {

    $catId = inputInt($_POST, 'cat_id');

    if ($catId <= 0 || !categoryExists($pdo, $catId)) {
        return "Choose a valid category.";
    }

    $count = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    if ($count <= 1) {
        return "You cannot delete the last category.";
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM archives WHERE cat_id = ?");
    $stmt->execute([$catId]);
    $files = (int)$stmt->fetchColumn();
    $stmt->closeCursor();

    if ($files > 0) {
        return "That category still has $files file(s). Move or delete them first.";
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE cat_id = ?");
        $stmt->execute([$catId]);
    } catch (PDOException $e) {
        // 23000 = a file was added to it a moment ago (foreign key RESTRICT)
        if ($e->getCode() === '23000') {
            return "That category still has files. Move or delete them first.";
        }
        throw $e;
    }

    return "Category deleted.";
}
