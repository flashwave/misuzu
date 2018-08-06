<?php
function starts_with(string $string, string $text): bool
{
    return substr($string, 0, strlen($text)) === $text;
}

function ends_with(string $string, string $text): bool
{
    return substr($string, 0 - strlen($text)) === $text;
}

function array_test(array $array, callable $func): bool
{
    foreach ($array as $value) {
        if (!$func($value)) {
            return false;
        }
    }

    return true;
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

function asset_url(string $path): string
{
    $realPath = realpath(__DIR__ . '/public/' . $path);

    if ($realPath === false) {
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

function get_country_code(string $ipAddr, string $fallback = 'XX'): string
{
    global $_msz_geoip;

    try {
        if (!$_msz_geoip) {
            $app = \Misuzu\Application::getInstance();
            $config = $app->getConfig();

            if ($config === null) {
                return $fallback;
            }

            $database_path = $config->get('GeoIP', 'database_path');

            if ($database_path === null) {
                return $fallback;
            }

            $_msz_geoip = new \GeoIp2\Database\Reader($database_path);
        }

        $record = $_msz_geoip->country($ipAddr);

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

function parse_markdown(string $text): string
{
    return \Misuzu\Parsers\MarkdownParser::instance()->parseText($text);
}

function parse_bbcode(string $text): string
{
    return \Misuzu\Parsers\BBCode\BBCodeParser::instance()->parseText($text);
}

function is_local_url(string $url): bool
{
    $length = strlen($url);

    if ($length < 1) {
        return false;
    }

    if ($url[0] === '/' && ($length > 1 ? $url[1] !== '/' : true)) {
        return true;
    }

    $prefix = 'http' . (empty($_SERVER['HTTPS']) ? '' : 's') . '://' . $_SERVER['HTTP_HOST'] . '/';
    return starts_with($url, $prefix);
}

function parse_text(string $text, string $parser): string
{
    switch (strtolower($parser)) {
        case 'md':
        case 'markdown':
            return \Misuzu\Parsers\MarkdownParser::instance()->parseText($text);

        case 'bb':
        case 'bbcode':
            return \Misuzu\Parsers\BBCode\BBCodeParser::instance()->parseText($text);

        default:
            return $text;
    }
}

function parse_line(string $line, string $parser): string
{
    switch (strtolower($parser)) {
        case 'md':
        case 'markdown':
            return \Misuzu\Parsers\MarkdownParser::instance()->parseLine($line);

        case 'bb':
        case 'bbcode':
            return \Misuzu\Parsers\BBCode\BBCodeParser::instance()->parseLine($line);

        default:
            return $line;
    }
}

function render_error(int $code, string $template = 'errors.%d'): string
{
    return render_info(null, $code, $template);
}

function render_info(?string $message, int $httpCode, string $template = 'errors.%d'): string
{
    http_response_code($httpCode);

    try {
        $tpl = \Misuzu\Application::getInstance()->getTemplating();

        $tpl->var('http_code', $httpCode);

        if (strlen($message)) {
            $tpl->var('message', $message);
        }

        $template = sprintf($template, $httpCode);

        if (!$tpl->exists($template, \Misuzu\TemplateEngine::TWIG_DEFAULT)) {
            $template = 'errors.master';
        }

        return $tpl->render(sprintf($template, $httpCode));
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

    if (strpos($url, '://') !== false) {
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

function html_colour(?int $colour, array $attribs = []): string
{
    $colour = $colour ?? colour_none();

    if (!$attribs) {
        $attribs['color'] = '%s';
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
        $url .= '?';

        foreach ($query as $key => $value) {
            if ($value) {
                $url .= urlencode($key) . '=' . urlencode($value) . '&';
            }
        }
    }

    return substr($url, 0, -1);
}

function camel_to_snake(string $camel): string
{
    return trim(strtolower(preg_replace('#([A-Z][a-z]+)#', '$1_', $camel)), '_');
}

function snake_to_camel(string $snake): string
{
    return str_replace('_', '', ucwords($snake, '_'));
}
