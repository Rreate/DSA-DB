<script>
// Early browser-side warning; PHP repeats this check on the server.
var uploadForm = document.querySelector('#dlg-add form');
var uploadInput = document.getElementById('upload-files');
var uploadSizeError = document.getElementById('upload-size-error');
var maxUploadBytes = 50 * 1024 * 1024;

if (uploadForm && uploadInput && uploadSizeError) {
  uploadForm.addEventListener('submit', function (e) {
    var oversized = false;
    for (var i = 0; i < uploadInput.files.length; i++) {
      if (uploadInput.files[i].size > maxUploadBytes) {
        oversized = true;
        break;
      }
    }
    uploadSizeError.style.display = oversized ? 'block' : 'none';
    if (oversized) e.preventDefault();
  });
  uploadInput.addEventListener('change', function () {
    uploadSizeError.style.display = 'none';
  });
}

// ---------- Elements (looked up once) ----------
var list = document.getElementById('file-list');
var menu = document.getElementById('ctx');
var overlay = document.getElementById('overlay');

var current = null;      // the file that was right-clicked (replaced each time)
var holdTimer = null;    // timer for press-and-hold on phones
var pickedRow = null;

// ---------- Find the .row that was touched ----------
function findRow(el) {
  while (el && el !== list) {
    if (el.classList && el.classList.contains('row')) {
      return el;
    }
    el = el.parentNode;
  }
  return null;
}

// ---------- Context menu ----------
function openMenu(row, x, y) {
  closeMenu();
  current = {
    id: row.getAttribute('data-id'),
    title: row.getAttribute('data-title'),
    ext: row.getAttribute('data-ext'),
    cat: row.getAttribute('data-cat')
  };
  pickedRow = row;
  row.classList.add('picked');

  menu.style.display = 'block';
  // keep the menu inside the screen
  if (x + menu.offsetWidth > window.innerWidth) { x = window.innerWidth - menu.offsetWidth - 8; }
  if (y + menu.offsetHeight > window.innerHeight) { y = window.innerHeight - menu.offsetHeight - 8; }
  menu.style.left = Math.max(8, x) + 'px';
  menu.style.top = Math.max(8, y) + 'px';
}

function closeMenu() {
  menu.style.display = 'none';
  if (pickedRow) {
    pickedRow.classList.remove('picked');
    pickedRow = null;
  }
}

// Desktop: right-click
list.addEventListener('contextmenu', function (e) {
  var row = findRow(e.target);
  if (!row) { return; }
  e.preventDefault();
  clearTimeout(holdTimer);
  openMenu(row, e.clientX, e.clientY);
});

// Mobile: press and hold for 0.6 seconds
function cancelHold() {
  clearTimeout(holdTimer);
  holdTimer = null;
}
list.addEventListener('touchstart', function (e) {
  var row = findRow(e.target);
  if (!row) { return; }
  var x = e.touches[0].clientX;
  var y = e.touches[0].clientY;
  cancelHold();
  holdTimer = setTimeout(function () {
    holdTimer = null;
    openMenu(row, x, y);
  }, 600);
}, { passive: true });
list.addEventListener('touchmove', cancelHold, { passive: true });
list.addEventListener('touchend', cancelHold);
list.addEventListener('touchcancel', cancelHold);

// ---------- Dialogs ----------
function showDialog(id) {
  if (!overlay) return;

  var targetDialog = document.getElementById(id);
  if (!targetDialog || !targetDialog.classList.contains('dialog')) {
    console.error('Cannot open missing dialog:', id);
    return;
  }

  var all = overlay.getElementsByClassName('dialog');
  for (var i = 0; i < all.length; i++) {
    all[i].style.display = 'none';
  }

  targetDialog.style.display = 'block';
  overlay.style.display = 'flex';
}

function closeDialog() {
  overlay.style.display = 'none';
  var forms = overlay.getElementsByTagName('form');
  for (var i = 0; i < forms.length; i++) {
    forms[i].reset();      // also clears any chosen files from the file input
  }
}


// Use delegated click handling so the buttons still work if their markup is moved
// or recreated later. Stop the browser's default action before opening the dialog.
document.addEventListener('click', function (e) {
  var opener = e.target.closest('[data-dialog]');
  if (!opener) return;

  e.preventDefault();
  var dialogId = opener.getAttribute('data-dialog');
  var dialog = document.getElementById(dialogId);

  if (!dialog || !overlay) {
    console.error('Dialog opener points to a missing dialog:', dialogId);
    return;
  }

  showDialog(dialogId);

  // Put keyboard focus inside the opened dialog.
  var firstField = dialog.querySelector('input:not([type="hidden"]), select, button, a');
  if (firstField) firstField.focus();
});

// The chooser's options open their dedicated dialogs.
document.addEventListener('click', function (e) {
  var choice = e.target.closest('[data-open-dialog]');
  if (!choice) return;

  e.preventDefault();
  var dialogId = choice.getAttribute('data-open-dialog');
  showDialog(dialogId);

  var dialog = document.getElementById(dialogId);
  var firstField = dialog && dialog.querySelector('input:not([type="hidden"]), select, button, a');
  if (firstField) firstField.focus();
});

// Every Cancel / Close button
var closers = overlay.getElementsByClassName('close-btn');
for (var j = 0; j < closers.length; j++) {
  closers[j].addEventListener('click', closeDialog);
}

// Click on the dark area outside a dialog
overlay.addEventListener('click', function (e) {
  if (e.target === overlay) { closeDialog(); }
});

// ---------- Menu choices open their own dialog ----------
menu.addEventListener('click', function (e) {
  var act = e.target.getAttribute('data-act');
  if (!act || !current) { return; }
  var fullName = current.title + '.' + current.ext;
  closeMenu();

  if (act === 'download') {
    document.getElementById('dl-text').textContent = 'Download "' + fullName + '"?';
    document.getElementById('dl-link').href = 'index.php?download=' + encodeURIComponent(current.id);
    showDialog('dlg-download');
  } else if (act === 'rename') {
    document.getElementById('rename-id').value = current.id;
    document.getElementById('rename-title').value = current.title;
    showDialog('dlg-rename');
    document.getElementById('rename-title').focus();
  } else if (act === 'recat') {
    document.getElementById('recat-id').value = current.id;
    document.getElementById('recat-select').value = current.cat;
    showDialog('dlg-recat');
  } else if (act === 'delete') {
    document.getElementById('del-id').value = current.id;
    document.getElementById('del-text').textContent = '"' + fullName + '" will be removed for good.';
    showDialog('dlg-delete');
  }
});

// Close the download dialog once the download starts
document.getElementById('dl-link').addEventListener('click', function () {
  setTimeout(closeDialog, 300);
});

// ---------- Close the menu on any outside action ----------
document.addEventListener('click', function (e) {
  if (!menu.contains(e.target)) { closeMenu(); }
});
list.addEventListener('scroll', closeMenu);
window.addEventListener('resize', closeMenu);
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    closeMenu();
    closeDialog();
  }
});
</script>
