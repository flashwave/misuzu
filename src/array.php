<?php
function array_test(array $array, callable $func): bool
{
    foreach ($array as $value) {
        if (!$func($value)) {
            return false;
        }
    }

    return true;
}

function array_apply(array $array, callable $func): array
{
    for ($i = 0; $i < count($array); $i++) {
        $array[$i] = $func($array[$i]);
    }

    return $array;
}
