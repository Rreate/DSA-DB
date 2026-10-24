
<?php
function mergeSortMulti($arr, $keys, $dirs = []) {

    //If the array has 1 or 0 elements
    if (count($arr) <= 1) {
        return $arr;
    }
    //Find the middle position
    $mid = intdiv(count($arr), 2);
    
    //Divide the array into two parts
    $left = array_slice($arr, 0, $mid);
    $right = array_slice($arr, $mid);
    
    //Sort the left part
    $left = mergeSortMulti($left, $keys, $dirs);
    
    //Sort the right part
    $right = mergeSortMulti($right, $keys, $dirs);
    
    //Prepare the merged array
    $result = [];
    
    $i = 0;
    $j = 0;
    
    //Compare elements from both arrays
    while ($i < count($left) && $j < count($right)) {

        $comparison = compareMulti(
            $left[$i],
            $right[$j],
            $keys,
            $dirs
        );
        if ($comparison <= 0) {

            $result[] = $left[$i];
            $i = $i + 1;

        } else {

            $result[] = $right[$j];
            $j = $j + 1;

        }
    }
    
    //Copy remaining elements from the left
    while ($i < count($left)) {

        $result[] = $left[$i];
        $i = $i + 1;

    }
    //Copy remaining elements from the right
    while ($j < count($right)) {

        $result[] = $right[$j];
        $j = $j + 1;

    }
    return $result;
    }
    
function compareMulti($a, $b, $keys, $dirs) {
	//Check each sorting key in order
    foreach ($keys as $index => $key) {
    	$direction = 'ASC';
    	
    	if (isset($dirs[$index])) {
            $direction = strtoupper($dirs[$index]);
        }
        //If values are equal, check the next key
        if ($a[$key] == $b[$key]) {
            continue;
        }
        //Compare the two values
        if ($a[$key] < $b[$key]) {
            $comparison = -1;
        } else {
            $comparison = 1;
        }
        
        //Reverse the result for descending order
        if ($direction == 'DESC') {
            $comparison = $comparison * -1;
        }
        return $comparison;
    }
    return 0;
}

?>
