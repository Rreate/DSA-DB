<?php
// POST dispatcher: CSRF check, run the action, redirect

$queryString = $_SERVER['QUERY_STRING'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = inputStr($_POST, 'csrf');

    if (
        empty($_POST) &&
        empty($_FILES) &&
        (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
    ) {

        $_SESSION['flash'] =
            "Upload rejected: request exceeds post_max_size.";

    } elseif (!hash_equals($_SESSION['csrf'], $token)) {

        $_SESSION['flash'] = "Your session expired. Please try again.";

    } else {

        $action = inputStr($_POST, 'action');

        try {

            switch ($action) {

                case 'upload':
                    $_SESSION['flash'] = doUpload($pdo);
                    break;

                case 'rename':
                    $_SESSION['flash'] = doRename($pdo);
                    break;

                case 'recat':
                    $_SESSION['flash'] = doRecategorize($pdo);
                    break;

                case 'delete':
                    $_SESSION['flash'] = doDelete($pdo);
                    break;

                case 'addcat':
                    $_SESSION['flash'] = doAddCategory($pdo);
                    break;

                default:
                    $_SESSION['flash'] = "Invalid action.";
            }

        } catch (PDOException $e) {

            error_log("DB error: " . $e->getMessage());
            $_SESSION['flash'] = "Database error. Please try again.";

        } catch (Throwable $e) {

            error_log("Action error: " . $e->getMessage());
            $_SESSION['flash'] = "Something went wrong. Please try again.";
        }
    }

    $pdo = null;

    header(
        "Location: index.php" .
        ($queryString !== '' ? '?' . $queryString : '')
    );

    exit;
}
