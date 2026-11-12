<?php
// Login, logout, and the "you must be logged in" gate.
// Runs before every other controller, so nothing below it is reachable
// without a valid login.

const MAX_LOGIN_FAILS = 5;     // wrong passwords allowed in a row...
const LOCK_SECONDS    = 300;   // ...before that account is locked this long

$loginError = '';
$action = ($_SERVER['REQUEST_METHOD'] === 'POST') ? inputStr($_POST, 'action') : '';

if ($action === 'login' || $action === 'logout') {

    if (!hash_equals($_SESSION['csrf'], inputStr($_POST, 'csrf'))) {

        $loginError = 'Your session expired. Please try again.';

    } elseif ($action === 'logout') {

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        header('Location: index.php');
        exit;

    } else {

        $name = mb_substr(trim(inputStr($_POST, 'username')), 0, 50);
        $pass = inputStr($_POST, 'password');

        try {
            $stmt = $pdo->prepare(
                "SELECT user_id, username, password_hash,
                        (locked_until IS NOT NULL AND locked_until > NOW()) AS locked,
                        failed_logins
                 FROM users WHERE username = ?"
            );
            $stmt->execute([$name]);
            $u = $stmt->fetch();
            $stmt->closeCursor();

            // Always run password_verify, so a wrong username takes as long as a wrong password
            $dummy = '$2y$10$zYLoR8vdjZ3sylv9kr0VROgfYxjutCc0syLsa3Qt115es3nhnSGMy';
            $ok = password_verify($pass, $u ? $u['password_hash'] : $dummy);

            if ($u && (int)$u['locked'] === 1) {

                $loginError = 'Too many failed attempts. Try again in a few minutes.';

            } elseif ($u && $ok) {

                $pdo->prepare("UPDATE users SET failed_logins = 0, locked_until = NULL WHERE user_id = ?")
                    ->execute([$u['user_id']]);

                if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                    $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?")
                        ->execute([password_hash($pass, PASSWORD_DEFAULT), $u['user_id']]);
                }

                session_regenerate_id(true);               // new session id on login
                $_SESSION['uid']   = (int)$u['user_id'];
                $_SESSION['uname'] = $u['username'];
                $_SESSION['csrf']  = bin2hex(random_bytes(16));
                header('Location: index.php');
                exit;

            } else {

                if ($u) {
                    $fails = (int)$u['failed_logins'] + 1;
                    if ($fails >= MAX_LOGIN_FAILS) {
                        $pdo->prepare("UPDATE users SET failed_logins = 0,
                                       locked_until = DATE_ADD(NOW(), INTERVAL " . (int)LOCK_SECONDS . " SECOND)
                                       WHERE user_id = ?")->execute([$u['user_id']]);
                    } else {
                        $pdo->prepare("UPDATE users SET failed_logins = ? WHERE user_id = ?")
                            ->execute([$fails, $u['user_id']]);
                    }
                }
                usleep(300000);   // slow down guessing
                $loginError = 'Wrong username or password.';
            }

        } catch (Throwable $e) {
            error_log("Login error: " . $e->getMessage());
            $loginError = 'Login is not available right now.';
        }
    }
}

// The gate: no login, no app
if (empty($_SESSION['uid'])) {
    $pdo = null;
    require __DIR__ . '/../views/login.php';
    exit;
}

$userId   = (int)$_SESSION['uid'];
$userName = (string)($_SESSION['uname'] ?? '');
