<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log in - File Manager</title>
<script>try{if(localStorage.getItem('theme')==='black')document.documentElement.setAttribute('data-theme','black')}catch(e){}</script>
<?php require __DIR__ . '/styles.php'; ?>
</head>
<body>
<div id="login-box">
  <svg id="logo" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
  <h1>File Manager</h1>
  <p>Log in to see your files.</p>
  <?php if ($loginError !== '') { ?><div class="flash error" role="alert"><?= h($loginError) ?></div><?php } ?>
  <form method="post" action="index.php">
    <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
    <input type="hidden" name="action" value="login">
    <label for="lg-user">Username</label>
    <input id="lg-user" type="text" name="username" maxlength="50" autocomplete="username" required autofocus>
    <label for="lg-pass">Password</label>
    <input id="lg-pass" type="password" name="password" autocomplete="current-password" required>
    <button type="submit" class="ok">Log in</button>
  </form>
</div>
</body>
</html>
