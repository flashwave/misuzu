<?php
namespace Misuzu;

use UnexpectedValueException;
use GeoIp2\Database\Reader as GeoIP;

/**
 * Handles the set up procedures.
 * @package Misuzu
 */
final class Application
{
    private static $instance = null;

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

    // used in some of the user functions still, fix that
    public static function getInstance(): Application
    {
        if (empty(self::$instance)) {
            throw new UnexpectedValueException('No instances.');
        }

        return self::$instance;
    }
}
