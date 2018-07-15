<?php
namespace Misuzu;

use Misuzu\Config\ConfigManager;
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
     * @var ConfigManager
     */
    private $configManager;

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
        ConfigManager $config,
        string $default = 'default'
    ) {
        if (self::hasInstance()) {
            throw new UnexpectedValueException('Only one instance of Database may exist.');
        }

        self::$instance = $this;
        $this->default = $default;
        $this->configManager = $config;
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

    public function addConnection(string $name): PDO
    {
        $section = "Database.{$name}";

        if (!$this->configManager->contains($section, 'driver')) {
            throw new InvalidArgumentException('Config section not found!');
        }

        $driver = $this->configManager->get($section, 'driver');

        if (!in_array($driver, self::SUPPORTED)) {
            throw new InvalidArgumentException('Unsupported driver.');
        }

        $dsn = $driver . ':';
        $options = [
            PDO::ATTR_CASE => PDO::CASE_NATURAL,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        switch ($driver) {
            case 'sqlite':
                if ($this->configManager->get($section, 'memory', 'bool', false)) {
                    $dsn .= ':memory:';
                } else {
                    $databasePath = realpath(
                        $this->configManager->get($section, 'database', 'string', __DIR__ . '/../store/misuzu.db')
                    );

                    if ($databasePath === false) {
                        throw new \UnexpectedValueException("Database does not exist.");
                    }

                    $dsn .= $databasePath . ';';
                }
                break;

            case 'mysql':
                $is_unix_socket = $this->configManager->contains($section, 'unix_socket');

                if ($is_unix_socket) {
                    $dsn .= 'unix_socket=' . $this->configManager->get($section, 'unix_socket', 'string') . ';';
                } else {
                    $dsn .= 'host=' . $this->configManager->get($section, 'host', 'string', self::DEFAULT_HOST) . ';';
                    $dsn .= 'port=' . $this->configManager->get($section, 'port', 'int', self::DEFAULT_PORT_MYSQL) . ';';
                }

                $dsn .= 'charset=' . (
                    $this->configManager->contains($section, 'charset')
                        ? $this->configManager->get($section, 'charset', 'string')
                        : 'utf8mb4'
                ) . ';';

                $dsn .= 'dbname=' . $this->configManager->get($section, 'database', 'string', 'misuzu') . ';';

                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "
                    SET SESSION
                        sql_mode='STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION',
                        time_zone = '+00:00';
                ";
                break;
        }

        $connection = new PDO(
            $dsn,
            $this->configManager->get($section, 'username', 'string', null),
            $this->configManager->get($section, 'password', 'string', null),
            $options
        );

        return $this->connections[$name] = $connection;
    }
}
