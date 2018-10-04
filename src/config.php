<?php
define('MSZ_CONFIG_STORE', '_msz_configuration_store');

function config_load(string $path, bool $isText = false): void
{
    $config = $isText
        ? parse_ini_string($path, true, INI_SCANNER_TYPED)
        : parse_ini_file($path, true, INI_SCANNER_TYPED);

    if (!is_array($GLOBALS[MSZ_CONFIG_STORE])) {
        $GLOBALS[MSZ_CONFIG_STORE] = [];
    }

    $GLOBALS[MSZ_CONFIG_STORE] = array_merge_recursive($GLOBALS[MSZ_CONFIG_STORE], $config);
}

function config_get(string $key, $default = null)
{
    $lastDot = strrpos($key, '.');

    if ($lastDot !== false) {
        $section = substr($key, 0, $lastDot);
        $key = substr($key, $lastDot + 1);
        return $GLOBALS[MSZ_CONFIG_STORE][$section][$key] ?? $default;
    }

    return $GLOBALS[MSZ_CONFIG_STORE][$key] ?? $default;
}
