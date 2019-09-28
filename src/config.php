<?php
define('MSZ_CFG_ANY', '');
define('MSZ_CFG_STR', 'string');
define('MSZ_CFG_INT', 'integer');
define('MSZ_CFG_BOOL', 'boolean');
define('MSZ_CFG_ARR', 'array');

define('MSZ_CFG_DEFAULTS', [
    MSZ_CFG_ANY => null,
    MSZ_CFG_STR => '',
    MSZ_CFG_INT => 0,
    MSZ_CFG_BOOL => false,
    MSZ_CFG_ARR => [],
]);

function config_store(?array $append = null): array {
    static $store = [];

    if(!is_null($append)) {
        $store = array_merge($store, $append);
    }

    return $store;
}

function config_init(): void {
    try {
        $dbconfig = \Misuzu\DB::prepare('SELECT * FROM `msz_config`')->fetchAll();
    } catch (PDOException $ex) {
        return;
    }

    $config = [];

    foreach($dbconfig as $record)
        $config[$record['config_name']] = unserialize($record['config_value']);

    config_store($config);
}

function config_get(string $key, string $type = MSZ_CFG_ANY, $default = null) {
    $value = config_store()[$key] ?? null;

    if($type !== MSZ_CFG_ANY && gettype($value) !== $type)
        $value = null;

    return $value ?? $default ?? MSZ_CFG_DEFAULTS[$type];
}

function config_set(string $key, $value, bool $soft = false): void {
    config_store([$key => $value]);

    if($soft)
        return;

    $value = serialize($value);
    $saveVal = \Misuzu\DB::prepare('
        INSERT INTO `msz_config`
            (`config_name`, `config_value`)
        VALUES
            (:name, :value_1)
        ON DUPLICATE KEY UPDATE
            `config_value` = :value_2
    ');
    $saveVal->bind('name', $key);
    $saveVal->bind('value_1', $value);
    $saveVal->bind('value_2', $value);
    $saveVal->execute();
}
