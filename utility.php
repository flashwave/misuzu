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

function json_encode_m($obj): string
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    return json_encode($obj);
}

function set_cookie_m(string $name, string $value, int $expires): void
{
    setcookie(
        "msz_{$name}",
        $value,
        time() + $expires,
        '/',
        '',
        !empty($_SERVER['HTTPS']),
        true
    );
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

// this should be rewritten to only load the database once per Application instance.
// for now this will do since the only time this function is called is once during registration.
// also make sure an instance of Application with config exists before calling this!
function get_country_code(string $ipAddr, string $fallback = 'XX'): string
{
    try {
        $app = \Misuzu\Application::getInstance();

        if (!$app->hasModule('config')) {
            return $fallback;
        }

        $database_path = $app->config->get('GeoIP', 'database_path');

        if ($database_path === null) {
            return $fallback;
        }

        $geoip = new \GeoIp2\Database\Reader($database_path);
        $record = $geoip->country($ipAddr);

        return $record->country->isoCode;
    } catch (\Exception $e) {
        // report error?
    }

    return $fallback;
}

function get_country_name(string $code): string
{
    switch (strtolower($code)) {
        case 'xx':
            return 'Unknown';

        case 'a1':
            return 'Anonymous Proxy';

        case 'a2':
            return 'Satellite Provider';

        default:
            return locale_get_display_region("-{$code}", 'en');
    }
}

// this is temporary, don't scream at me for using md5
// BIG TODO: make these functions not dependent on sessions so they can be used outside of those.
function tmp_csrf_verify(string $token, ?\Misuzu\Users\Session $session = null): bool
{
    if ($session === null) {
        $session = \Misuzu\Application::getInstance()->getSession();
    }

    return hash_equals(tmp_csrf_token($session), $token);
}

function tmp_csrf_token(?\Misuzu\Users\Session $session = null): string
{
    if ($session === null) {
        $session = \Misuzu\Application::getInstance()->getSession();
    }

    return md5($session->session_key);
}

function crop_image_centred_path(string $filename, int $target_width, int $target_height): \Imagick
{
    return crop_image_centred(new \Imagick($filename), $target_width, $target_height);
}

function crop_image_centred(Imagick $image, int $target_width, int $target_height): Imagick
{
    $image->setImageFormat($image->getNumberImages() > 1 ? 'gif' : 'png');
    $image = $image->coalesceImages();

    $width = $image->getImageWidth();
    $height = $image->getImageHeight();

    if ($width > $height) {
        $resize_width = $width * $target_height / $height;
        $resize_height = $target_height;
    } else {
        $resize_width = $target_width;
        $resize_height = $height * $target_width / $width;
    }

    do {
        $image->resizeImage(
            $resize_width,
            $resize_height,
            Imagick::FILTER_LANCZOS,
            0.9
        );

        $image->cropImage(
            $target_width,
            $target_height,
            ($resize_width - $target_width) / 2,
            ($resize_height - $target_height) / 2
        );

        $image->setImagePage(
            $target_width,
            $target_height,
            0,
            0
        );
    } while ($image->nextImage());

    return $image->deconstructImages();
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
