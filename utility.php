<?php
// both of these are provided by illuminate/database already but i feel like it makes sense to add these definitions regardless

if (!function_exists('starts_with')) {
    function starts_with($string, $text) {
        return substr($string, 0, strlen($text)) === $text;
    }
}

if (!function_exists('ends_with')) {
    function ends_with($string, $text) {
        return substr($string, 0 - strlen($text)) === $text;
    }
}

function dechex_pad($value, $padding = 2) {
    return str_pad(dechex($value), $padding, '0', STR_PAD_LEFT);
}
