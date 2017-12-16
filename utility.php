<?php
// both of these are provided by illuminate/database already but i feel like it makes sense to add these definitions regardless

if (!function_exists('starts_with')) {
    function starts_with(string $string, string $text): bool {
        return substr($string, 0, strlen($text)) === $text;
    }
}

if (!function_exists('ends_with')) {
    function ends_with(string $string, string $text): bool {
        return substr($string, 0 - strlen($text)) === $text;
    }
}

function dechex_pad(int $value, int $padding = 2): string {
    return str_pad(dechex($value), $padding, '0', STR_PAD_LEFT);
}

function array_rand_value(array $array, $fallback = null) {
    if (!$array)
        return $fallback;

    return $array[array_rand($array)];
}

function has_flag(int $flags, int $flag): bool {
    return ($flags & $flag) > 0;
}
