<?php
function use_legacy_style(): void
{
    tpl_var('use_legacy_style', true);
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

function fix_path_separator(string $path, string $separator = DIRECTORY_SEPARATOR, array $separators = ['/', '\\']): string
{
    return str_replace($separators, $separator, rtrim($path, implode($separators)));
}

function safe_delete(string $path): void
{
    $path = realpath($path);

    if (empty($path)) {
        return;
    }

    if (is_dir($path)) {
        rmdir($path);
        return;
    }

    unlink($path);
}

// mkdir + recursion
function create_directory(string $path): string
{
    if (is_file($path)) {
        return '';
    }

    if (is_dir($path)) {
        return realpath($path);
    }

    $on_windows = running_on_windows();
    $path = fix_path_separator($path);
    $split_path = explode(DIRECTORY_SEPARATOR, $path);
    $existing_path = $on_windows ? '' : DIRECTORY_SEPARATOR;

    foreach ($split_path as $path_part) {
        $existing_path .= $path_part . DIRECTORY_SEPARATOR;

        if ($on_windows && mb_substr($path_part, 1, 2) === ':\\') {
            continue;
        }

        if (!file_exists($existing_path)) {
            mkdir($existing_path);
        }
    }

    return ($path = realpath($path)) === false ? '' : $path;
}

function build_path(string ...$path): string
{
    for ($i = 0; $i < count($path); $i++) {
        $path[$i] = fix_path_separator($path[$i]);
    }

    return implode(DIRECTORY_SEPARATOR, $path);
}

function check_mx_record(string $email): bool
{
    $domain = mb_substr(mb_strstr($email, '@'), 1);
    return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
}

function asset_url(string $path): string
{
    $realPath = realpath(MSZ_ROOT . '/public/' . $path);

    if ($realPath === false || !file_exists($realPath)) {
        return $path;
    }

    return $path . '?' . filemtime($realPath);
}

function dechex_pad(int $value, int $padding = 2): string
{
    return str_pad(dechex($value), $padding, '0', STR_PAD_LEFT);
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
    return starts_with(mb_strtolower(PHP_OS), 'win');
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

function is_local_url(string $url): bool
{
    $length = mb_strlen($url);

    if ($length < 1) {
        return false;
    }

    if ($url[0] === '/' && ($length > 1 ? $url[1] !== '/' : true)) {
        return true;
    }

    $prefix = 'http' . (empty($_SERVER['HTTPS']) ? '' : 's') . '://' . $_SERVER['HTTP_HOST'] . '/';
    return starts_with($url, $prefix);
}

function render_error(int $code, string $template = 'errors.%d'): string
{
    return render_info(null, $code, $template);
}

function render_info(?string $message, int $httpCode, string $template = 'errors.%d'): string
{
    http_response_code($httpCode);

    try {
        tpl_var('http_code', $httpCode);

        if (mb_strlen($message)) {
            tpl_var('message', $message);
        }

        $template = sprintf($template, $httpCode);

        if (!tpl_exists($template)) {
            $template = 'errors.master';
        }

        return tpl_render(sprintf($template, $httpCode));
    } catch (Exception $ex) {
        echo $ex->getMessage();
        return $message ?? '';
    }
}

function render_info_or_json(bool $json, string $message, int $httpCode = 200, string $template = 'errors.%d'): string
{
    $error = $httpCode >= 400;
    http_response_code($httpCode);

    if ($json) {
        return json_encode([($error ? 'error' : 'message') => $message]);
    }

    return render_info($message, $httpCode, $template);
}

function html_link(string $url, ?string $content = null, $attributes = []): string
{
    $content = $content ?? $url;
    $attributes = array_merge(
        is_string($attributes) ? ['class' => $attributes] : $attributes,
        ['href' => $url]
    );

    if (mb_strpos($url, '://') !== false) {
        $attributes['target'] = '_blank';
        $attributes['rel'] = 'noreferrer noopener';
    }

    $html = '<a';

    foreach ($attributes as $name => $value) {
        $value = str_replace('"', '\"', $value);
        $html .= " {$name}=\"{$value}\"";
    }

    $html .= ">{$content}</a>";

    return $html;
}

function html_colour(?int $colour, $attribs = '--user-colour'): string
{
    $colour = $colour ?? colour_none();

    if (is_string($attribs)) {
        $attribs = [
            $attribs => '%s',
        ];
    }

    if (!$attribs) {
        $attribs = [
            'color' => '%s',
            '--user-colour' => '%s',
        ];
    }

    $css = '';
    $value = colour_get_css($colour);

    foreach ($attribs as $name => $format) {
        $css .= $name . ':' . sprintf($format, $value) . ';';
    }

    return $css;
}

function url_construct(string $path, array $query = [], string $host = ''): string
{
    $url = $host . $path;

    if (count($query)) {
        $url .= mb_strpos($path, '?') !== false ? '&' : '?';

        foreach ($query as $key => $value) {
            if ($value) {
                $url .= urlencode($key) . '=' . urlencode($value) . '&';
            }
        }
    }

    return mb_substr($url, 0, -1);
}

function is_user_int($value): bool
{
    return ctype_digit(strval($value));
}
