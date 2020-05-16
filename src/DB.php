<?php
namespace Misuzu;

use PDO;
use Misuzu\Database\Database;

final class DB {
    private static $instance;

    public const PREFIX = 'msz_';
    public const QUERY_SELECT = 'SELECT %2$s FROM `' . self::PREFIX . '%1$s` AS %1$s';

    public const ATTRS = [
        PDO::ATTR_CASE => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "
            SET SESSION
                sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION',
                time_zone = '+00:00';
        ",
    ];

    public static function init(...$args) {
        self::$instance = new Database(...$args);
    }

    public static function __callStatic(string $name, array $args) {
        return self::$instance->{$name}(...$args);
    }

    public static function buildDSN(array $vars): string {
        $dsn = ($vars['driver'] ?? 'mysql') . ':';

        foreach($vars as $key => $value) {
            if($key === 'driver' || $key === 'username' || $key === 'password')
                continue;
            if($key === 'database')
                $key = 'dbname';

            $dsn .= $key . '=' . $value . ';';
        }

        return $dsn;
    }
}
