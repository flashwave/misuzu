<?php
namespace Misuzu;

use Illuminate\Database\Capsule\Manager as LaravelDatabaseManager;
use Misuzu\Config\ConfigManager;
use InvalidArgumentException;

/**
 * Class Database
 * @package Misuzu
 */
class Database extends LaravelDatabaseManager
{
    /**
     * @var ConfigManager
     */
    private $configManager;

    /**
     * Array of supported abstraction layers, primarily depends on what Illuminate\Database supports in reality.
     */
    private const SUPPORTED_DB_ALS = [
        'mysql',
        'sqlite',
        'pgsql',
        'sqlsrv',
    ];

    /**
     * The default port for MySQL.
     */
    private const DEFAULT_PORT_MYSQL = 3306;

    /**
     * The default port for PostgreSQL.
     */
    private const DEFAULT_PORT_PGSQL = 5432;

    /**
     * Default port for Microsoft SQL Server.
     */
    private const DEFAULT_PORT_MSSQL = 1433;

    /**
     * Default hostname.
     */
    private const DEFAULT_HOST = '127.0.0.1';

    /**
     * Database constructor.
     * @param ConfigManager $config
     * @param string        $default
     * @param bool          $startEloquent
     * @param bool          $setAsGlobal
     */
    public function __construct(
        ConfigManager $config,
        string $default = 'default',
        bool $startEloquent = true,
        bool $setAsGlobal = true
    ) {
        $this->configManager = $config;
        parent::__construct();

        $this->container['config']['database.default'] = $default;

        if ($startEloquent) {
            $this->bootEloquent();
        }

        if ($setAsGlobal) {
            $this->setAsGlobal();
        }
    }

    /**
     * Creates a new connection using a ConfigManager instance.
     * @param string $section
     * @param string $name
     */
    public function addConnectionFromConfig(string $section, string $name = 'default'): void
    {
        if (!$this->configManager->contains($section, 'driver')) {
            throw new InvalidArgumentException('Config section not found!');
        }

        $driver = $this->configManager->get($section, 'driver');

        if (!in_array($driver, self::SUPPORTED_DB_ALS)) {
            throw new InvalidArgumentException('Unsupported driver.');
        }

        $args = [
            'driver' => $driver,
            'database' => $this->configManager->get($section, 'database', 'string', 'misuzu'),
            'prefix' => $this->configManager->contains($section, 'prefix')
                ? $this->configManager->get($section, 'prefix', 'string')
                : '',
        ];

        switch ($driver) {
            case 'mysql':
                $is_unix_socket = $this->configManager->contains($section, 'unix_socket');

                $args['host'] = $is_unix_socket
                    ? ''
                    : $this->configManager->get($section, 'host', 'string', self::DEFAULT_HOST);

                $args['port'] = $is_unix_socket
                    ? self::DEFAULT_PORT_MYSQL
                    : $this->configManager->get($section, 'port', 'int', self::DEFAULT_PORT_MYSQL);

                $args['username'] = $this->configManager->get($section, 'username', 'string');
                $args['password'] = $this->configManager->get($section, 'password', 'string');
                $args['unix_socket'] = $is_unix_socket
                    ? $this->configManager->get($section, 'unix_socket', 'string')
                    : '';

                // these should probably be locked to these types
                $args['charset'] = $this->configManager->contains($section, 'charset')
                    ? $this->configManager->get($section, 'charset', 'string')
                    : 'utf8mb4';

                $args['collation'] = $this->configManager->contains($section, 'collation')
                    ? $this->configManager->get($section, 'collation', 'string')
                    : 'utf8mb4_bin';

                $args['strict'] = true;
                $args['engine'] = null;
                break;

            case 'pgsql':
                $is_unix_socket = $this->configManager->contains($section, 'unix_socket');

                $args['host'] = $is_unix_socket
                    ? ''
                    : $this->configManager->get($section, 'host', 'string', self::DEFAULT_HOST);

                $args['port'] = $is_unix_socket
                    ? self::DEFAULT_PORT_PGSQL
                    : $this->configManager->get($section, 'port', 'int', self::DEFAULT_PORT_PGSQL);

                $args['username'] = $this->configManager->get($section, 'username', 'string');
                $args['password'] = $this->configManager->get($section, 'password', 'string');

                $args['unix_socket'] = $is_unix_socket
                    ? $this->configManager->get($section, 'unix_socket', 'string')
                    : '';

                // these should probably be locked to these types
                $args['charset'] = $this->configManager->contains($section, 'charset')
                    ? $this->configManager->get($section, 'charset', 'string')
                    : 'utf8';

                $args['schema'] = 'public';
                $args['sslmode'] = 'prefer';
                break;

            case 'sqlsrv':
                $args['host'] = $this->configManager->get($section, 'host', 'string', self::DEFAULT_HOST);
                $args['port'] = $this->configManager->get($section, 'port', 'int', self::DEFAULT_PORT_MSSQL);
                $args['username'] = $this->configManager->get($section, 'username', 'string');
                $args['password'] = $this->configManager->get($section, 'password', 'string');

                // these should probably be locked to these types
                $args['charset'] = $this->configManager->contains($section, 'charset')
                    ? $this->configManager->get($section, 'charset', 'string')
                    : 'utf8';
                break;
        }

        $this->addConnection($args, $name);
    }
}
