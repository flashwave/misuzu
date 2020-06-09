<?php
namespace Misuzu;

use PDO;
use PDOException;

final class Config {
    public const TYPE_ANY = '';
    public const TYPE_STR = 'string';
    public const TYPE_INT = 'integer';
    public const TYPE_BOOL = 'boolean';
    public const TYPE_ARR = 'array';

    public const DEFAULTS = [
        self::TYPE_ANY => null,
        self::TYPE_STR => '',
        self::TYPE_INT => 0,
        self::TYPE_BOOL => false,
        self::TYPE_ARR => [],
    ];

    private static $config = [];

    public static function init(): void {
        try {
            $config = DB::prepare('SELECT * FROM `msz_config`')->fetchAll();
        } catch(PDOException $ex) {
            return;
        }


        foreach($config as $record) {
            self::$config[$record['config_name']] = unserialize($record['config_value']);
        }
    }

    public static function get(string $key, string $type = self::TYPE_ANY, $default = null) {
        $value = self::$config[$key] ?? null;

        if($type !== self::TYPE_ANY && gettype($value) !== $type)
            $value = null;

        return $value ?? $default ?? self::DEFAULTS[$type];
    }

    public static function has(string $key): bool {
        return array_key_exists($key, self::$config);
    }

    public static function set(string $key, $value, bool $soft = false): void {
        self::$config[$key] = $value;

        if(!$soft) {
            $value = serialize($value);

            DB::prepare('
                REPLACE INTO `msz_config`
                    (`config_name`, `config_value`)
                VALUES
                    (:name, :value)
            ')->bind('name', $key)
              ->bind('value', $value)
              ->execute();
        }
    }
}
