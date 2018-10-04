<?php
define('MSZ_CONFIG_STORE', '_msz_configuration_store');

function config_load(string $path, bool $isText = false): void
{
    $config = $isText
        ? parse_ini_string($path, true, INI_SCANNER_TYPED)
        : parse_ini_file($path, true, INI_SCANNER_TYPED);

    if (!is_array($GLOBALS[MSZ_CONFIG_STORE] ?? null)) {
        $GLOBALS[MSZ_CONFIG_STORE] = [];
    }

    $GLOBALS[MSZ_CONFIG_STORE] = array_merge_recursive($GLOBALS[MSZ_CONFIG_STORE], $config);
}

function config_get(string ...$key)
{
    $value = $GLOBALS[MSZ_CONFIG_STORE];

    for ($i = 0; $i < count($key); $i++) {
        if (empty($value[$key[$i]])) {
            return null;
        }

        $value = $value[$key[$i]];
    }

    return $value;
}

function config_get_default($default, string ...$key)
{
    return config_get(...$key) ?? $default;
}
