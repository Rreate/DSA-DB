<?php if ($pages > 1) { ?>
    <nav id="pager" aria-label="Pages">
      <?= $page > 1 ? pageLink($page - 1, '&laquo; Prev') : '<span class="pg off">&laquo; Prev</span>' ?>
      <?php
        // 1 ... (page-2 .. page+2) ... last
        $show = array_unique(array_filter(
            array_merge([1, $pages], range(max(1, $page - 2), min($pages, $page + 2))),
            fn($n) => $n >= 1 && $n <= $pages
        ));
        sort($show);
        $prev = 0;
        foreach ($show as $n) {
            if ($n - $prev > 1) { echo '<span class="pg gap">&hellip;</span>'; }
            echo $n === $page
                ? '<span class="pg cur" aria-current="page">' . $n . '</span>'
                : pageLink($n, (string)$n);
            $prev = $n;
        }
      ?>
      <?= $page < $pages ? pageLink($page + 1, 'Next &raquo;') : '<span class="pg off">Next &raquo;</span>' ?>
    </nav>
<?php } ?>
