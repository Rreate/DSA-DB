<!-- Dialog overlay: only one dialog is visible at a time -->
<div id="overlay">

  <div class="dialog" id="dlg-choose">
    <h2>What would you like to add?</h2>
    <p>Choose an action to continue.</p>
    <div class="buttons" style="justify-content:stretch;flex-direction:column;">
      <button type="button" data-open-dialog="dlg-add" class="ok" style="width:100%;">＋ Add files</button>
      <button type="button" data-open-dialog="dlg-cat" style="width:100%;">＋ Add category</button>
      <button type="button" class="close-btn">Cancel</button>
    </div>
  </div>

  <div class="dialog" id="dlg-add">
    <h2>Add files</h2>
    <form method="post" enctype="multipart/form-data" action="index.php?<?= h($queryString) ?>">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
      <input type="hidden" name="action" value="upload">
      <label>Category</label>
      <select name="cat_id" required>
        <?php foreach ($categories as $c) { ?>
          <option value="<?= (int)$c['cat_id'] ?>" <?= $cat === (int)$c['cat_id'] ? 'selected' : '' ?>><?= h($c['category']) ?></option>
        <?php } ?>
      </select>
      <label>Files (up to <?= maxUploadMiB() ?> MiB each)</label>
      <input type="file" id="upload-files" name="files[]" multiple required>
      <p id="upload-size-error" role="alert" style="display:none;color:#991b1b;margin-top:8px;font-size:0.85rem;">One or more selected files exceed the <?= maxUploadMiB() ?> MiB limit. Remove those files before uploading.</p>
      <div class="buttons">
        <button type="button" class="close-btn">Cancel</button>
        <button type="submit" class="ok">Add files</button>
      </div>
    </form>
  </div>

  <div class="dialog" id="dlg-cat">
    <h2>New category</h2>
    <form method="post" action="index.php?<?= h($queryString) ?>">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
      <input type="hidden" name="action" value="addcat">
      <label>Category name</label>
      <input type="text" name="category" maxlength="30" required>
      <div class="buttons">
        <button type="button" class="close-btn">Cancel</button>
        <button type="submit" class="ok">Add category</button>
      </div>
    </form>
  </div>

  <div class="dialog" id="dlg-download">
    <h2>Download file</h2>
    <p id="dl-text"></p>
    <div class="buttons">
      <button type="button" class="close-btn">Cancel</button>
      <a id="dl-link" class="ok" href="#">Download</a>
    </div>
  </div>

  <div class="dialog" id="dlg-rename">
    <h2>Rename file</h2>
    <form method="post" action="index.php?<?= h($queryString) ?>">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
      <input type="hidden" name="action" value="rename">
      <input type="hidden" name="id" id="rename-id">
      <label>New name (the extension stays the same)</label>
      <input type="text" name="title" id="rename-title" maxlength="200" required>
      <div class="buttons">
        <button type="button" class="close-btn">Cancel</button>
        <button type="submit" class="ok">Save name</button>
      </div>
    </form>
  </div>

  <div class="dialog" id="dlg-recat">
    <h2>Re-categorize file</h2>
    <form method="post" action="index.php?<?= h($queryString) ?>">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
      <input type="hidden" name="action" value="recat">
      <input type="hidden" name="id" id="recat-id">
      <label>Move to category</label>
      <select name="cat_id" id="recat-select">
        <?php foreach ($categories as $c) { ?>
          <option value="<?= (int)$c['cat_id'] ?>"><?= h($c['category']) ?></option>
        <?php } ?>
      </select>
      <div class="buttons">
        <button type="button" class="close-btn">Cancel</button>
        <button type="submit" class="ok">Move file</button>
      </div>
    </form>
  </div>

  <div class="dialog" id="dlg-delete">
    <h2>Delete file</h2>
    <p id="del-text"></p>
    <form method="post" action="index.php?<?= h($queryString) ?>">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" id="del-id">
      <div class="buttons">
        <button type="button" class="close-btn">Keep file</button>
        <button type="submit" class="bad">Delete file</button>
      </div>
    </form>
  </div>
</div>
