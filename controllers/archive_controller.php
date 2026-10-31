<?php
// Actions on files: upload, rename, re-categorize, delete


// UPLOAD

function doUpload($pdo): string {

    // (A request over post_max_size is detected earlier, in post_controller.php)

    if (!isset($_FILES['files']['name'])) {
        return "No files were selected.";
    }

    $catId = inputInt($_POST, 'cat_id');

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

    $saved     = 0;
    $overLimit = 0;   // bigger than this app's limit
    $overIni   = 0;   // refused by PHP (upload_max_filesize / post_max_size)
    $skipped   = 0;   // wrong type, nothing selected, disk error, ...

    foreach ($names as $i => $originalName) {

        $storedName = '';

        try {

            $uploadError = (int)($errors[$i] ?? UPLOAD_ERR_NO_FILE);
            $fileSize    = (int)($sizes[$i] ?? 0);

            if ($uploadError !== UPLOAD_ERR_OK) {
                if (
                    $uploadError === UPLOAD_ERR_INI_SIZE ||
                    $uploadError === UPLOAD_ERR_FORM_SIZE
                ) {
                    $overIni++;
                } else {
                    $skipped++;
                }
                continue;
            }

            if ($fileSize > MAX_UPLOAD_BYTES) {
                $overLimit++;
                continue;
            }

            $ext = strtolower(
                pathinfo($originalName, PATHINFO_EXTENSION)
            );

            if (getGroup($ext) === '') {
                $skipped++;
                continue;
            }

            // Generate a unique storage filename
            $storedName = bin2hex(random_bytes(12)) . '.dat';
            $dest = UPLOAD_DIR . '/' . $storedName;

            if (!move_uploaded_file($tmps[$i], $dest)) {
                $skipped++;
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

            // Do not leave a file on disk that has no database row
            if ($storedName !== '' && is_file(UPLOAD_DIR . '/' . $storedName)) {
                @unlink(UPLOAD_DIR . '/' . $storedName);
            }

            $skipped++;
        }
    }

    $msg = "$saved file(s) added.";

    if ($overLimit > 0) {
        $msg .= " $overLimit file(s) rejected: maximum size is " . maxUploadMiB() . " MiB per file.";
    }

    if ($overIni > 0) {
        $msg .= " $overIni file(s) rejected by the server's own limits (upload_max_filesize = "
              . ini_get('upload_max_filesize') . ", post_max_size = " . ini_get('post_max_size')
              . "). Raise them in php.ini to allow " . maxUploadMiB() . " MiB uploads.";
    }

    if ($skipped > 0) {
        $msg .= " $skipped other file(s) skipped.";
    }

    return $msg;
}


// RENAME

function doRename($pdo) {

    $id    = inputInt($_POST, 'id');
    $title = cleanTitle(inputStr($_POST, 'title'));

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

    $id    = inputInt($_POST, 'id');
    $catId = inputInt($_POST, 'cat_id');

    if ($id <= 0 || !categoryExists($pdo, $catId)) {
        return "Invalid file or category.";
    }

    if (!getArchive($pdo, $id)) {
        return "File not found.";
    }

    $stmt = $pdo->prepare(
        "UPDATE archives SET cat_id = ? WHERE archive_id = ?"
    );

    $stmt->execute([$catId, $id]);

    return "File moved to the new category.";
}


// DELETE
//


function doDelete($pdo) {

    $id = inputInt($_POST, 'id');

    if ($id <= 0) {
        return "Invalid archive ID.";
    }

    $row = getArchive($pdo, $id);

    if (!$row) {
        return "File not found.";
    }

    $path   = diskPath($row);
    $backup = $path . '.deleting';
    $moved  = false;

    if (is_file($path)) {
        if (!@rename($path, $backup)) {
            error_log("File rename error: $path");
            return "File could not be prepared for deletion.";
        }
        $moved = true;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM archives WHERE archive_id = ?");
        $stmt->execute([$id]);
    } catch (Throwable $e) {
        error_log("Archive deletion error: " . $e->getMessage());

        // Nothing was deleted: put the file back
        if ($moved && is_file($backup) && !@rename($backup, $path)) {
            error_log("File restoration failed: $backup");
        }

        return "Deletion failed. Nothing was removed.";
    }

    // The record is gone. Remove the set-aside file.
    if ($moved && !@unlink($backup)) {
        error_log("Temporary file cleanup failed: $backup");
        return "File removed from the list, but its stored copy could not be cleaned up.";
    }

    return "File deleted.";
}
