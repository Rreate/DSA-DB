<?php
// Output helpers used by the views

// Escape HTML output
function h($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

// Format file sizes
function formatSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $size = (float)$bytes;
    $i = 0;

    while ($size >= 1024 && $i < 3) {
        $size /= 1024;
        $i++;
    }

    return $i === 0
        ? $bytes . ' B'
        : number_format($size, 1) . ' ' . $units[$i];
}


// SORT HEADER BUTTON


function headerButton($col, $label) {

    global $sort, $dir;

    $newDir = 'ASC';
    $class = 'hbtn';

    if ($sort === $col) {

        $class .= ' on ' . strtolower($dir);

        if ($dir === 'ASC') {
            $newDir = 'DESC';
        }
    }

    return '<a role="button" class="' . $class .
        '" href="index.php?' .
        h(makeQuery(['sort' => $col, 'dir' => $newDir])) .
        '">' . $label . '</a>';
}
