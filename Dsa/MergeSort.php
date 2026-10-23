<?php
function mergeSortMulti($arr, $keys, $dirs = []){
  if(count($arr) <= 1) return $arr;
  $mid = intdiv(count($arr), 2);
  $left = mergeSortMulti(array_slice($arr, 0, $mid), $keys, $dirs);
  $right = mergeSortMulti(array_slice($arr, $mid), $keys, $dirs);

  $res=[]; $i=$j=0;
  while($i<count($left) && $j<count($right)){
    $cmp = compareMulti($left[$i], $right[$j], $keys, $dirs);
    if($cmp <= 0) $res[] = $left[$i++];
    else $res[] = $right[$j++];
  }
  while($i<count($left)) $res[] = $left[$i++];
  while($j<count($right)) $res[] = $right[$j++];
  
  // blank temp vars
  $left=[]; $right=[]; unset($left, $right);
  return $res;
}

function compareMulti($a, $b, $keys, $dirs){
  foreach($keys as $idx => $key){
    $dir = $dirs[$idx] ?? 'ASC';
    if($a[$key] == $b[$key]) continue; // equal, check next key
    $result = $a[$key] <=> $b[$key];
    return $dir == 'DESC' ? -$result : $result;
  }
  return 0;
}
