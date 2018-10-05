<?php
use GeoIp2\Database\Reader;

define('MSZ_GEOIP_INSTANCE_STORE', '_msz_maxmind_geoip');
define('MSZ_GEOIP_CACHE_STORE', '_msz_geoip_cache');

function geoip_init(?string $database = null): void
{
    if (!empty($GLOBALS[MSZ_GEOIP_INSTANCE_STORE])) {
        $GLOBALS[MSZ_GEOIP_INSTANCE_STORE]->close();
    }

    $GLOBALS[MSZ_GEOIP_INSTANCE_STORE] = new Reader($database ?? config_get('GeoIP', 'database_path'));
}

function geoip_cache(string $section, string $ipAddress, callable $value)
{
    if (empty($GLOBALS[MSZ_GEOIP_CACHE_STORE][$ipAddress][$section])) {
        $GLOBALS[MSZ_GEOIP_CACHE_STORE][$ipAddress][$section] = $value();
    }

    return $GLOBALS[MSZ_GEOIP_CACHE_STORE][$ipAddress][$section] ?? null;
}

function geoip_country(string $ipAddress)
{
    return geoip_cache('country', $ipAddress, function () use ($ipAddress) {
        return $GLOBALS[MSZ_GEOIP_INSTANCE_STORE]->country($ipAddress);
    });
}
