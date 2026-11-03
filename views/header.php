<div id="header">
  <div id="header-nav">
    <svg id="logo" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
    
  </div>
  <div id="stats"><?= (int)$totalFiles ?> file(s) &middot; <?= h(formatSize($totalSize)) ?><?= $pages > 1 ? ' &middot; page ' . (int)$page . ' of ' . (int)$pages : '' ?> &middot; Right-click, long-press or press Enter on a file for actions</div>
  <button id="theme" type="button">Black</button>
  <form id="logout-form" method="post" action="index.php">
    <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
    <input type="hidden" name="action" value="logout">
    <span id="who"><?= h($userName) ?></span>
    <button type="submit" id="logout">Log out</button>
  </form>
</div>
