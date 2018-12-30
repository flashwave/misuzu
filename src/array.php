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

if (!function_exists('array_key_first')) {
    // https://secure.php.net/manual/en/function.array-key-first.php#123301
    function array_key_first(array $array)
    {
        if (count($array)) {
            reset($array);
            return key($array);
        }

        return null;
    }
}
