<?php
namespace Misuzu;

use Misuzu\Database\Database;

final class DB {
    private static $instance;

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
