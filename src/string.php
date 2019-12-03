<?php
function starts_with(string $string, string $text, bool $multibyte = true): bool {
    $strlen = $multibyte ? 'mb_strlen' : 'strlen';
    $substr = $multibyte ? 'mb_substr' : 'substr';
    return $substr($string, 0, $strlen($text)) === $text;
}

function ends_with(string $string, string $text, bool $multibyte = true): bool {
    $strlen = $multibyte ? 'mb_strlen' : 'strlen';
    $substr = $multibyte ? 'mb_substr' : 'substr';
    return $substr($string, 0 - $strlen($text)) === $text;
}

function first_paragraph(string $text, string $delimiter = "\n"): string {
    $index = mb_strpos($text, $delimiter);
    return $index === false ? $text : mb_substr($text, 0, $index);
}

function camel_to_snake(string $camel): string {
    return trim(mb_strtolower(preg_replace('#([A-Z][a-z]+)#', '$1_', $camel)), '_');
}

function snake_to_camel(string $snake): string {
    return str_replace('_', '', ucwords($snake, '_'));
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
    if($bytes < 1) {
        return '0 B';
    }

    $divider = $decimal ? 1000 : 1024;
    $exp = floor(log($bytes) / log($divider));
    $bytes = $bytes / pow($divider, floor($exp));
    $symbol = $symbols[$exp];

    return sprintf("%.2f %s%sB", $bytes, $symbol, $symbol !== '' && !$decimal ? 'i' : '');
}
