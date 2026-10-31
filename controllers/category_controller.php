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
