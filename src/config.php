<?php
function config_store(?array $append = null): array {
    static $store = [];

    if(!is_null($append)) {
        $store = array_merge_recursive($store, $append);
    }

    return $store;
}

function config_load(string $path, bool $isText = false): void {
    $config = $isText
        ? parse_ini_string($path, true, INI_SCANNER_TYPED)
        : parse_ini_file($path, true, INI_SCANNER_TYPED);

    config_store($config);
}

function config_get(string ...$key) {
    $value = config_store();

    for($i = 0; $i < count($key); $i++) {
        if(empty($value[$key[$i]])) {
            return null;
        }

        $value = $value[$key[$i]];
    }

    return $value;
}

function config_get_default($default, string ...$key) {
    return config_get(...$key) ?? $default;
}
