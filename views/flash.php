<?php if ($loadError !== '' || $flash !== '') { ?>
<div id="content-area">
  <?php if ($loadError !== '') { ?><div class="flash error" role="alert"><?= h($loadError) ?></div><?php } ?>
  <?php if ($flash !== '') { ?><div class="flash" role="status"><?= h($flash) ?></div><?php } ?>
</div>
<?php } ?>
