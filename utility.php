<?php
function array_test(array $array, callable $func): bool {
    foreach($array as $value)
        if(!$func($value))
            return false;
    return true;
}

function array_apply(array $array, callable $func): array {
    for($i = 0; $i < count($array); ++$i)
        $array[$i] = $func($array[$i]);
    return $array;
}

function array_bit_or(array $array1, array $array2): array {
    foreach($array1 as $key => $value)
        $array1[$key] |= $array2[$key] ?? 0;
    return $array1;
}

function array_rand_value(array $array) {
    return $array[mt_rand(0, count($array) - 1)];
}

function array_find(array $array, callable $callback) {
    foreach($array as $item)
        if($callback($item))
            return $item;
    return null;
}

function clamp($num, int $min, int $max): int {
    return max($min, min($max, intval($num)));
}

function starts_with(string $haystack, string $needle, bool $multibyte = true): bool {
    $strlen = $multibyte ? 'mb_strlen' : 'strlen';
    $substr = $multibyte ? 'mb_substr' : 'substr';
    return $substr($haystack, 0, $strlen($needle)) === $needle;
}

function ends_with(string $haystack, string $needle, bool $multibyte = true): bool {
    $strlen = $multibyte ? 'mb_strlen' : 'strlen';
    $substr = $multibyte ? 'mb_substr' : 'substr';
    return $substr($haystack, 0 - $strlen($needle)) === $needle;
}

if(!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool { return starts_with($haystack, $needle, false); }
    function str_ends_with(string $haystack, string $needle): bool { return ends_with($haystack, $needle, false); }
}

function first_paragraph(string $text, string $delimiter = "\n"): string {
    $index = mb_strpos($text, $delimiter);
    return $index === false ? $text : mb_substr($text, 0, $index);
}

function unique_chars(string $input, bool $multibyte = true): int {
    $chars = [];
    $strlen = $multibyte ? 'mb_strlen' : 'strlen';
    $substr = $multibyte ? 'mb_substr' : 'substr';
    $length = $strlen($input);

    for($i = 0; $i < $length; $i++) {
        $current = $substr($input, $i, 1);

        if(!in_array($current, $chars, true)) {
            $chars[] = $current;
        }
    }

    return count($chars);
}

function byte_symbol(int $bytes, bool $decimal = false, array $symbols = ['', 'K', 'M', 'G', 'T', 'P', 'E', 'Z', 'Y']): string {
    if($bytes < 1)
        return '0 B';

    $divider = $decimal ? 1000 : 1024;
    $exp = floor(log($bytes) / log($divider));
    $bytes = $bytes / pow($divider, $exp);
    $symbol = $symbols[$exp];

    return sprintf("%.2f %s%sB", $bytes, $symbol, $symbol !== '' && !$decimal ? 'i' : '');
}

function get_country_name(string $code, string $locale = 'en'): string {
    if($code === 'xx')
        return 'Unknown';
    return \Locale::getDisplayRegion("-{$code}", $locale);
}

/*** THE DEPRECATION NATION (LINE) ***/

// render_error, render_info and render_info_or_json should be redone a bit better
// following a uniform format so there can be a global handler for em

function render_error(int $code, string $template = 'errors.%d'): string {
    return render_info(null, $code, $template);
}

function render_info(?string $message, int $httpCode, string $template = 'errors.%d'): string {
    http_response_code($httpCode);

    try {
        \Misuzu\Template::set('http_code', $httpCode);

        if(mb_strlen($message)) {
            \Misuzu\Template::set('message', $message);
        }

        $template = sprintf($template, $httpCode);

        /*if(!tpl_exists($template)) {
            $template = 'errors.master';
        }*/

        return \Misuzu\Template::renderRaw(sprintf($template, $httpCode));
    } catch(Exception $ex) {
        echo $ex->getMessage();
        return $message ?? '';
    }
}

function render_info_or_json(bool $json, string $message, int $httpCode = 200, string $template = 'errors.%d'): string {
    $error = $httpCode >= 400;
    http_response_code($httpCode);

    if($json) {
        return json_encode([($error ? 'error' : 'message') => $message, 'success' => $error]);
    }

    return render_info($message, $httpCode, $template);
}

function html_colour(?int $colour, $attribs = '--user-colour'): string {
    $colour = $colour == null ? \Misuzu\Colour::none() : new \Misuzu\Colour($colour);

    if(is_string($attribs)) {
        $attribs = [
            $attribs => '%s',
        ];
    }

    if(!$attribs) {
        $attribs = [
            'color' => '%s',
            '--user-colour' => '%s',
        ];
    }

    $css = '';
    $value = $colour->getCSS();

    foreach($attribs as $name => $format) {
        $css .= $name . ':' . sprintf($format, $value) . ';';
    }

    return $css;
}
