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
        $expires,
        '/',
        '',
        !empty($_SERVER['HTTPS']),
        true
    );
}

function password_entropy(string $password): int
{
    return count(count_chars(utf8_decode($password), 1)) * 8;
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
        $config = $app->getConfig();

        if ($config === null) {
            return $fallback;
        }

        $database_path = $config->get('GeoIP', 'database_path');

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
function tmp_csrf_verify(string $token): bool
{
    return hash_equals(tmp_csrf_token(), $token);
}

function tmp_csrf_token(): string
{
    return md5($_COOKIE['msz_sid'] ?? 'this is very insecure lmao');
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

function running_on_windows(): bool
{
    return starts_with(strtolower(PHP_OS), 'win');
}

function first_paragraph(string $text, string $delimiter = "\n"): string
{
    $index = mb_strpos($text, $delimiter);
    return $index === false ? $text : mb_substr($text, 0, $index);
}

function pdo_prepare_array_update(array $keys, bool $useKeys = false, string $format = '%s'): string
{
    return pdo_prepare_array($keys, $useKeys, sprintf($format, '`%1$s` = :%1$s'));
}

function pdo_prepare_array(array $keys, bool $useKeys = false, string $format = '`%s`'): string
{
    $parts = [];

    if ($useKeys) {
        $keys = array_keys($keys);
    }

    foreach ($keys as $key) {
        $parts[] = sprintf($format, $key);
    }

    return implode(', ', $parts);
}
