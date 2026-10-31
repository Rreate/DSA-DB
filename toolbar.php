    <!-- Dropdown filters: category, modified, type -->
    <form id="toolbar" method="get" action="index.php">
      <input type="hidden" name="sort" value="<?= h($sort) ?>">
      <input type="hidden" name="dir" value="<?= h($dir) ?>">

      <label>Category
        <select name="cat" onchange="this.form.submit()">
          <option value="0">All categories</option>
          <?php foreach ($categories as $c) { ?>
            <option value="<?= (int)$c['cat_id'] ?>" <?= $cat === (int)$c['cat_id'] ? 'selected' : '' ?>><?= h($c['category']) ?></option>
          <?php } ?>
        </select>
      </label>

      <label>Modified
        <select name="mod" onchange="this.form.submit()">
          <option value="">Any time</option>
          <option value="day"   <?= $mod === 'day' ? 'selected' : '' ?>>Last 24 hours</option>
          <option value="week"  <?= $mod === 'week' ? 'selected' : '' ?>>Last week</option>
          <option value="month" <?= $mod === 'month' ? 'selected' : '' ?>>Last month</option>
        </select>
      </label>

      <label>Type
        <select name="type" onchange="this.form.submit()">
          <option value="">All types</option>
          <option value="presentation" <?= $type === 'presentation' ? 'selected' : '' ?>>Presentation</option>
          <optgroup label="Documents">
            <option value="pdf"  <?= $type === 'pdf' ? 'selected' : '' ?>>PDF</option>
            <option value="docs" <?= $type === 'docs' ? 'selected' : '' ?>>Docs</option>
          </optgroup>
          <option value="spreadsheet" <?= $type === 'spreadsheet' ? 'selected' : '' ?>>Spreadsheets</option>
          <option value="video" <?= $type === 'video' ? 'selected' : '' ?>>Video</option>
          <option value="audio" <?= $type === 'audio' ? 'selected' : '' ?>>Audio</option>
          <option value="image" <?= $type === 'image' ? 'selected' : '' ?>>Image</option>
        </select>
        
      </label>
      <div id="sidebar">
    <button type="button" id="btn-pick" data-dialog="dlg-choose">＋</button>
  </div>
      <noscript><button type="submit">Apply</button></noscript>
    </form>
