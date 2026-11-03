<?php


function mergeSortMulti($arr, $keys, $dirs = []) {
    // 0 or 1 items: already sorted
    if (count($arr) <= 1) {
        return $arr;
    }

    // Split in the middle
    $mid   = intdiv(count($arr), 2);
    $left  = mergeSortMulti(array_slice($arr, 0, $mid), $keys, $dirs);
    $right = mergeSortMulti(array_slice($arr, $mid), $keys, $dirs);

    // Merge the two sorted halves
    $result = [];
    $i = 0;
    $j = 0;
    $nLeft  = count($left);
    $nRight = count($right);

    while ($i < $nLeft && $j < $nRight) {
        // "<= 0" keeps equal items in their original order (stable)
        if (compareMulti($left[$i], $right[$j], $keys, $dirs) <= 0) {
            $result[] = $left[$i++];
        } else {
            $result[] = $right[$j++];
        }
    }

    // Add whatever is left over
    while ($i < $nLeft)  { $result[] = $left[$i++]; }
    while ($j < $nRight) { $result[] = $right[$j++]; }

    return $result;
}

function compareMulti($a, $b, $keys, $dirs) {
    foreach ($keys as $index => $key) {
        $dir  = $dirs[$index] ?? 'ASC';
        $valA = $a[$key] ?? null;
        $valB = $b[$key] ?? null;

        // Same value: look at the next key
        if ($valA === $valB) {
            continue;
        }

        if (is_string($valA) && is_string($valB)) {
            // Natural, case-insensitive order: file2 comes before file10
            $result = strnatcasecmp($valA, $valB);
            if ($result === 0) {
                continue;
            }
        } else {
            $result = ($valA < $valB) ? -1 : 1;
        }

        // DESC flips the result
        return $dir === 'DESC' ? -$result : $result;
    }

    return 0; // all keys equal
}
