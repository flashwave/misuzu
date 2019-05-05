<?php
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

    if (is_file($path)) {
        unlink($path);
    }
}

// mkdir but it fails silently
function mkdirs(string $path, bool $recursive = false, int $mode = 0777): bool
{
    if (file_exists($path)) {
        return true;
    }

    return mkdir($path, $mode, $recursive);
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

// render_error, render_info and render_info_or_json should be redone a bit better
// following a uniform format so there can be a global handler for em

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
        return json_encode([($error ? 'error' : 'message') => $message, 'success' => $error]);
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
