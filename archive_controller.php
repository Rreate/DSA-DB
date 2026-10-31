<?php
// Actions on files: upload, rename, re-categorize, delete


// UPLOAD


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


// RENAME

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


// RE-CATEGORIZE

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


// DELETE


function doDelete($pdo) {

    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    if (!$id || $id <= 0) {
        return "Invalid archive ID.";
    }

    $row = getArchive($pdo, $id);

    if (!$row) {
        return "File not found.";
    }

    $path = diskPath($row);

    // Check physical file.
    if (!is_file($path)) {
        return "Physical file not found.";
    }

    // Create temporary backup filename.
    $backup = $path . '.deleting';

    // Temporarily rename instead of permanently deleting.
    if (!rename($path, $backup)) {
        error_log("File rename error: $path");

        return "File could not be prepared for deletion.";
    }

    try {

        // Delete database record.
        $stmt = $pdo->prepare(
            "DELETE FROM archives WHERE archive_id = ?"
        );

        $stmt->execute([$id]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException("Database deletion failed.");
        }

        // Database deletion succeeded.
        // Permanently remove temporary file.
        if (!unlink($backup)) {
            error_log("Temporary file cleanup failed: $backup");

            return "Database record deleted, but temporary file remains.";
        }

        return "File deleted.";

    } catch (Throwable $e) {

        // Restore original file if database deletion fails.
        if (is_file($backup)) {

            if (!rename($backup, $path)) {
                error_log("File restoration failed: $backup");
            }
        }

        error_log("Archive deletion error: " . $e->getMessage());

        return "Deletion failed. Original file restoration was attempted.";
    }
}