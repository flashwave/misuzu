<?php
namespace Misuzu\Net;

use GeoIp2\Database\Reader as GeoIPDBReader;

final class GeoIP {
    private static $geoip = null;
    private static $geoipDbPath = null;

    public static function init(string $dbPath): void {
        self::$geoipDbPath = $dbPath;
    }

    public static function getReader(): GeoIPDBReader {
        if(self::$geoip === null)
            self::$geoip = new GeoIPDBReader(self::$geoipDbPath);
        return self::$geoip;
    }

    public static function resolveCountry(string $ipAddress) {
        return self::getReader()->country($ipAddress);
    }
}
