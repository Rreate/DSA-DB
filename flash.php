<div id="content-area">
  <?php if ($loadError !== '') { ?><div id="flash" class="error"><?= h($loadError) ?></div><?php } ?>
  <?php if ($flash !== '') { ?><div id="flash"><?= h($flash) ?></div><?php } ?>
</div>
