    <!-- Header: Name, Size and Date are sort buttons. Path is never sorted. -->
    <div id="file-header" class="grid">
      <div class="c-id" id="col-id">ID</div>
      <div class="c-name" id="col-name"><?= headerButton('name', 'Name') ?></div>
      <div class="c-cat" id="col-category">Category</div>
      <div class="c-path" id="col-path">Path</div>
      <div class="c-size" id="col-size"><?= headerButton('size', 'Size') ?></div>
      <div class="c-date" id="col-date"><?= headerButton('date', 'Date created') ?></div>
    </div>

    <div id="file-list">
      <?php if (count($rows) == 0) { ?>
        <div class="empty">No files match these filters. Choose Add files to upload one.</div>
      <?php } ?>

      <?php foreach ($rows as $r) { ?>
        <div class="row grid"
             data-id="<?= $r['archive_id'] ?>"
             data-title="<?= h($r['title']) ?>"
             data-ext="<?= h($r['file_type']) ?>"
             data-cat="<?= (int)$r['cat_id'] ?>">
          <div class="c-id"><?= $r['archive_id'] ?></div>
          <div class="c-name" title="<?= h($r['title'] . '.' . $r['file_type']) ?>"><?= h($r['title'] . '.' . $r['file_type']) ?></div>
          <div class="c-cat"><?= h($r['category']) ?></div>
          <div class="c-path"><?= h($r['file_path']) ?></div>
          <div class="c-size"><?= h(formatSize($r['file_size'])) ?></div>
          <div class="c-date"><?= h(date('Y-m-d H:i:s', $r['ts'])) ?></div>
        </div>
      <?php } ?>
    </div>
