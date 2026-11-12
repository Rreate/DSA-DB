<?php
// ONE-TIME INSTALLER. Run it once in the browser, then delete this file.
//
// It uses an admin MySQL account (never stored) to:
//   1. create the database and tables (config/schema.sql) and upgrade an old one,
//   2. create a LIMITED MySQL user for the app (SELECT/INSERT/UPDATE/DELETE only),
//   3. create the first app login,
//   4. protect the uploads folder, write config/local.php and lock the installer.

session_start();

$lockFile  = __DIR__ . '/config/install.lock';
$localFile = __DIR__ . '/config/local.php';

require_once __DIR__ . '/config/installer.php';

if (empty($_SESSION['inst_csrf'])) { $_SESSION['inst_csrf'] = bin2hex(random_bytes(16)); }

$log = []; $error = ''; $done = false;
$locked = is_file($lockFile);

if (!$locked && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['inst_csrf'], (string)($_POST['csrf'] ?? ''))) {
        $error = "Session expired. Reload and try again.";
    } else {
        $in = [];
        foreach (['db_host','db_name','root_user','root_pass','app_user','app_pass','admin_name','admin_pass'] as $k) {
            $in[$k] = is_string($_POST[$k] ?? null) ? $_POST[$k] : '';
        }
        try { $log = runInstall($in, $localFile, $lockFile); $done = true; $locked = true; }
        catch (Throwable $ex) { $error = $ex->getMessage(); }
    }
}
$v = fn($k, $d = '') => e($_POST[$k] ?? $d);
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install</title>
<style>
body{font-family:system-ui,sans-serif;background:#f3f4f6;margin:0;padding:2rem 1rem;color:#1f2937}
.box{max-width:520px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:1.5rem 1.75rem}
h1{margin:0 0 .25rem;font-size:1.3rem} h2{font-size:.95rem;margin:1.4rem 0 .4rem;color:#2563eb}
p.s{color:#6b7280;font-size:.85rem;margin:.2rem 0}
label{display:block;font-size:.8rem;color:#6b7280;margin:.7rem 0 .2rem}
input{width:100%;box-sizing:border-box;padding:8px 10px;border:1px solid #d1d5db;border-radius:8px;font:inherit}
button{margin-top:1.2rem;padding:9px 16px;border:0;border-radius:8px;background:#2563eb;color:#fff;font:inherit;cursor:pointer}
.err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:10px;border-radius:8px;margin-top:1rem;font-size:.9rem}
.ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:10px 14px;border-radius:8px;margin-top:1rem;font-size:.9rem}
.ok li{margin:.2rem 0}
</style></head><body><div class="box">
<h1>Install File Manager</h1>
<?php if ($done) { ?>
  <div class="ok"><strong>Installed.</strong><ul><?php foreach ($log as $l) { ?><li><?= e($l) ?></li><?php } ?></ul></div>
  <p class="s"><strong>Now delete install.php</strong> from the server, then <a href="index.php">open the app</a>.</p>
<?php } elseif ($locked) { ?>
  <div class="err">Already installed (config/install.lock exists). Delete install.php. To re-run on purpose, delete that lock file first.</div>
<?php } else { ?>
  <p class="s">Runs once. The admin MySQL account below is used for this request only and is not saved.</p>
  <?php if ($error !== '') { ?><div class="err"><?= e($error) ?></div><?php } ?>
  <form method="post" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= e($_SESSION['inst_csrf']) ?>">
    <h2>1. MySQL admin account (one time)</h2>
    <label>Host</label><input name="db_host" value="<?= $v('db_host','localhost') ?>" required>
    <label>Admin user</label><input name="root_user" value="<?= $v('root_user','root') ?>" required>
    <label>Admin password</label><input name="root_pass" type="password" value="">
    <h2>2. Database &amp; limited app account</h2>
    <label>Database name</label><input name="db_name" value="<?= $v('db_name','archive') ?>" required>
    <label>App MySQL user (gets SELECT/INSERT/UPDATE/DELETE only)</label><input name="app_user" value="<?= $v('app_user','archive_app') ?>" required>
    <label>App MySQL password (leave empty to generate a random one)</label><input name="app_pass" type="password" value="">
    <h2>3. First login for the website</h2>
    <label>Login name</label><input name="admin_name" value="<?= $v('admin_name','admin') ?>" required>
    <label>Password (min 8 characters)</label><input name="admin_pass" type="password" minlength="8" required>
    <button type="submit">Install</button>
  </form>
<?php } ?>
</div></body></html>
