<?php
namespace Misuzu;

use PDO;
use PDOStatement;
use InvalidArgumentException;
use UnexpectedValueException;

final class Database
{
    /**
     * Array of supported abstraction layers.
     */
    private const SUPPORTED = [
        'mysql',
        'sqlite',
    ];

    /**
     * Default hostname.
     */
    private const DEFAULT_HOST = '127.0.0.1';

    /**
     * The default port for MySQL.
     */
    private const DEFAULT_PORT_MYSQL = 3306;

    /**
     * @var Database
     */
    private static $instance;

    /**
     * @var PDO[]
     */
    private $connections = [];

    /**
     * @var string
     */
    private $default;

    public static function getInstance(): Database
    {
        if (!self::hasInstance()) {
            throw new UnexpectedValueException('No instance of Database exists yet.');
        }

        return self::$instance;
    }

    public static function hasInstance(): bool
    {
        return self::$instance instanceof static;
    }

    public function __construct(
        array $connections,
        string $default = 'default'
    ) {
        if (self::hasInstance()) {
            throw new UnexpectedValueException('Only one instance of Database may exist.');
        }

        self::$instance = $this;
        $this->default = $default;

        foreach ($connections as $name => $info) {
            $this->addConnection($info, $name);
        }
    }

    public static function connection(?string $name = null): PDO
    {
        return self::getInstance()->getConnection($name);
    }

    public static function prepare(string $statement, ?string $connection = null, $options = []): PDOStatement
    {
        return self::connection($connection)->prepare($statement, $options);
    }

    public static function query(string $statement, ?string $connection = null): PDOStatement
    {
        return self::connection($connection)->query($statement);
    }

    public static function exec(string $statement, ?string $connection = null)
    {
        return self::connection($connection)->exec($statement);
    }

    public static function lastInsertId(?string $name = null, ?string $connection = null): string
    {
        return self::connection($connection)->lastInsertId($name);
    }

    public static function queryCount(?string $connection = null): int
    {
        return (int)Database::query('SHOW SESSION STATUS LIKE "Questions"', $connection)->fetch()['Value'];
    }

    public function getConnection(?string $name = null): PDO
    {
        $name = $name ?? $this->default;
        return $this->connections[$name] ?? $this->addConnection($name);
    }

    public function addConnection(array $info, string $name): PDO
    {
        if (!array_key_exists('driver', $info)) {
            throw new InvalidArgumentException('Config section not found!');
        }

        if (!in_array($info['driver'], self::SUPPORTED)) {
            throw new InvalidArgumentException('Unsupported driver.');
        }

        $dsn = $info['driver'] . ':';
        $options = [
            PDO::ATTR_CASE => PDO::CASE_NATURAL,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        switch ($info['driver']) {
            case 'sqlite':
                if ($info['memory']) {
                    $dsn .= ':memory:';
                } else {
                    $databasePath = realpath($info['database'] ?? __DIR__ . '/../store/misuzu.db');

                    if ($databasePath === false) {
                        throw new UnexpectedValueException("Database does not exist.");
                    }
                }
                break;

            case 'mysql':
                if ($info['unix_socket'] ?? false) {
                    $dsn .= 'unix_socket=' . $info['unix_socket'] . ';';
                } else {
                    $dsn .= 'host=' . ($info['host'] ?? self::DEFAULT_HOST) . ';';
                    $dsn .= 'port=' . intval($info['port'] ?? self::DEFAULT_PORT_MYSQL) . ';';
                }

                $dsn .= 'charset=' . ($info['charset'] ?? 'utf8mb4') . ';';
                $dsn .= 'dbname=' . ($info['database'] ?? 'misuzu') . ';';

                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "
                    SET SESSION
                        sql_mode='STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION',
                        time_zone = '+00:00';
                ";
                break;
        }

        $connection = new PDO(
            $dsn,
            ($info['username'] ?? null),
            ($info['password'] ?? null),
            $options
        );

        return $this->connections[$name] = $connection;
    }
}
