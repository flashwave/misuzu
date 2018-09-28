<?php
function starts_with(string $string, string $text): bool
{
    return mb_substr($string, 0, mb_strlen($text)) === $text;
}

function ends_with(string $string, string $text): bool
{
    return mb_substr($string, 0 - mb_strlen($text)) === $text;
}

function first_paragraph(string $text, string $delimiter = "\n"): string
{
    $index = mb_strpos($text, $delimiter);
    return $index === false ? $text : mb_substr($text, 0, $index);
}

function camel_to_snake(string $camel): string
{
    return trim(mb_strtolower(preg_replace('#([A-Z][a-z]+)#', '$1_', $camel)), '_');
}

function snake_to_camel(string $snake): string
{
    return str_replace('_', '', ucwords($snake, '_'));
}
