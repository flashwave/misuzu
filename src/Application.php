<?php
namespace Misuzu;

use UnexpectedValueException;
use Swift_Mailer;
use Swift_NullTransport;
use Swift_SmtpTransport;
use Swift_SendmailTransport;
use GeoIp2\Database\Reader as GeoIP;

/**
 * Handles the set up procedures.
 * @package Misuzu
 */
final class Application
{
    private static $instance = null;

    /**
     * Array of database connection names, first in the list is assumed to be the default.
     */
    private const DATABASE_CONNECTIONS = [
        'mysql-main',
    ];

    private $geoipInstance = null;

    public function __construct()
    {
        if (!empty(self::$instance)) {
            throw new UnexpectedValueException('An Application has already been set up.');
        }

        self::$instance = $this;
    }

    /**
     * Gets a data storage path.
     * @return string
     */
    public function getStoragePath(): string
    {
        return create_directory(config_get_default(MSZ_ROOT . '/store', 'Storage', 'path'));
    }

    /**
     * Sets up the database module.
     */
    public function startDatabase(): void
    {
        if (Database::hasInstance()) {
            throw new UnexpectedValueException('Database has already been started.');
        }

        $connections = [];

        foreach (self::DATABASE_CONNECTIONS as $name) {
            $connections[$name] = config_get_default([], "Database.{$name}");
        }

        new Database($connections, self::DATABASE_CONNECTIONS[0]);
    }

    /**
     * Sets up the caching stuff.
     */
    public function startCache(): void
    {
        if (Cache::hasInstance()) {
            throw new UnexpectedValueException('Cache has already been started.');
        }

        new Cache(
            config_get('Cache', 'host'),
            config_get('Cache', 'port'),
            config_get('Cache', 'database'),
            config_get('Cache', 'password'),
            config_get_default('', 'Cache', 'prefix')
        );
    }

    public function startGeoIP(): void
    {
        if (!empty($this->geoipInstance)) {
            return;
        }

        $this->geoipInstance = new GeoIP(config_get_default('', 'GeoIP', 'database_path'));
    }

    public function getGeoIP(): GeoIP
    {
        if (empty($this->geoipInstance)) {
            $this->startGeoIP();
        }

        return $this->geoipInstance;
    }

    public static function geoip(): GeoIP
    {
        return self::getInstance()->getGeoIP();
    }

    public function getAvatarProps(): array
    {
        return [
            'max_width' => intval(config_get_default(1000, 'Avatar', 'max_width')),
            'max_height' => intval(config_get_default(1000, 'Avatar', 'max_height')),
            'max_size' => intval(config_get_default(500000, 'Avatar', 'max_filesize')),
        ];
    }

    public function getBackgroundProps(): array
    {
        return [
            'max_width' => intval(config_get_default(3840, 'Avatar', 'max_width')),
            'max_height' => intval(config_get_default(2160, 'Avatar', 'max_height')),
            'max_size' => intval(config_get_default(1000000, 'Avatar', 'max_filesize')),
        ];
    }

    public function underLockdown(): bool
    {
        return boolval(config_get_default(false, 'Auth', 'lockdown'));
    }

    public function disableRegistration(): bool
    {
        return $this->underLockdown()
            || $this->getPrivateInfo()['enabled']
            || boolval(config_get_default(false, 'Auth', 'prevent_registration'));
    }

    public function getPrivateInfo(): array
    {
        return config_get_default(['enabled' => false], 'Private');
    }

    // used in some of the user functions still, fix that
    public static function getInstance(): Application
    {
        if (empty(self::$instance)) {
            throw new UnexpectedValueException('No instances.');
        }

        return self::$instance;
    }
}
