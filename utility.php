<?php
// both of these are provided by illuminate/database already
// but i feel like it makes sense to add these definitions regardless

if (!function_exists('starts_with')) {
    function starts_with(string $string, string $text): bool
    {
        return substr($string, 0, strlen($text)) === $text;
    }
}

if (!function_exists('ends_with')) {
    function ends_with(string $string, string $text): bool
    {
        return substr($string, 0 - strlen($text)) === $text;
    }
}

function password_entropy(string $password): int
{
    return count(count_chars(utf8_decode($password), 1)) * log(256, 2);
}

function check_mx_record(string $email): bool
{
    $domain = substr(strstr($email, '@'), 1);
    return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
}

function dechex_pad(int $value, int $padding = 2): string
{
    return str_pad(dechex($value), $padding, '0', STR_PAD_LEFT);
}

function array_rand_value(array $array, $fallback = null)
{
    if (!$array) {
        return $fallback;
    }

    return $array[array_rand($array)];
}

function has_flag(int $flags, int $flag): bool
{
    return ($flags & $flag) > 0;
}

function byte_symbol($bytes, $decimal = false)
{
    if ($bytes < 1) {
        return "0 B";
    }

    $divider = $decimal ? 1000 : 1024;
    $symbols = ['', 'K', 'M', 'G', 'T', 'P', 'E', 'Z', 'Y'];

    $exp = floor(log($bytes) / log($divider));
    $bytes = $bytes / pow($divider, floor($exp));
    $symbol = $symbols[$exp];

    return sprintf("%.2f %s%sB", $bytes, $symbol, $symbol !== '' && !$decimal ? 'i' : '');
}

function get_country_code(string $ipAddr, string $fallback = 'XX'): string
{
    if (function_exists("geoip_country_code_by_name")) {
        try {
            $code = geoip_country_code_by_name($ipAddr);

            if ($code) {
                return $code;
            }
        } catch (\Exception $e) {
        }
    }

    return $fallback;
}

function is_int_ex($value, int $boundary_low, int $boundary_high): bool
{
    return is_int($value) && $value >= $boundary_low && $value <= $boundary_high;
}

function is_sbyte($value): bool
{
    return is_int_ex($value, -0x80, 0x7F);
}

function is_byte($value): bool
{
    return is_int_ex($value, 0x0, 0xFF);
}

function is_int16($value): bool
{
    return is_int_ex($value, -0x8000, 0x7FFF);
}

function is_uint16($value): bool
{
    return is_int_ex($value, 0x0, 0xFFFF);
}

function is_int32($value): bool
{
    return is_int_ex($value, -0x80000000, 0x7FFFFFFF);
}

function is_uint32($value): bool
{
    return is_int_ex($value, 0x0, 0xFFFFFFFF);
}

function is_int64($value): bool
{
    return is_int_ex($value, -0x8000000000000000, 0x7FFFFFFFFFFFFFFF);
}
