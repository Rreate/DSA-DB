<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>File Manager</title>
<?php require __DIR__ . '/styles.php'; ?>
</head>
<body>

<?php require __DIR__ . '/header.php'; ?>

<?php require __DIR__ . '/flash.php'; ?>

<div id="main-container">
  <div id="file-manager">
<?php require __DIR__ . '/toolbar.php'; ?>

<?php require __DIR__ . '/file_list.php'; ?>
  </div>

<?php require __DIR__ . '/context_menu.php'; ?>

<?php require __DIR__ . '/dialogs.php'; ?>
</div>

<?php require __DIR__ . '/scripts.php'; ?>

</body>
</html>
