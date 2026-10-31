<?php
// Actions on categories

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
