<?php
// File download (streams the file and exits)

if (isset($_GET['download'])) {

    $handle = null;

    try {

        $row = getArchive($pdo, (int)$_GET['download']);

        if (!$row || !is_file(diskPath($row))) {
            http_response_code(404);
            die("File not found.");
        }

        $path = diskPath($row);
        $size = filesize($path);

        $niceName = $row['title'] . '.' . $row['file_type'];
        $safeName = preg_replace('/[\r\n"\\\\\/]/', '_', $niceName);

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open download file.");
        }

        $pdo = null;
        session_write_close();

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header("Content-Type: application/octet-stream");
        header(
            "Content-Disposition: attachment; filename=\"" .
            $safeName . "\"; filename*=UTF-8''" . rawurlencode($niceName)
        );
        header("Content-Length: " . $size);

        while (!feof($handle)) {
            echo fread($handle, 8192);
            flush();
        }

    } catch (Throwable $e) {

        error_log("Download error: " . $e->getMessage());

        if (!headers_sent()) {
            http_response_code(500);
            echo "The file could not be downloaded.";
        }

    } finally {

        if (is_resource($handle)) {
            fclose($handle);
        }

        $pdo = null;
    }

    exit;
}
