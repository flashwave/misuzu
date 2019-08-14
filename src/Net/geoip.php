<?php
use GeoIp2\Database\Reader as GeoIPDBReader;

function geoip_init(?string $database = null): void {
    $existing = geoip_instance();

    if(!empty($existing)) {
        $existing->close();
    }

    geoip_instance(new GeoIPDBReader($database ?? config_get('geoip.database')));
}

function geoip_instance(?GeoIPDBReader $newInstance = null): ?GeoIPDBReader {
    static $instance = null;

    if(!empty($newInstance)) {
        $instance = $newInstance;
    }

    return $instance;
}

function geoip_cache(string $section, string $ipAddress, callable $value) {
    static $memo = [];

    if(empty($meme[$ipAddress][$section])) {
        $memo[$ipAddress][$section] = $value();
    }

    return $memo[$ipAddress][$section] ?? null;
}

function geoip_country(string $ipAddress) {
    return geoip_cache('country', $ipAddress, function () use ($ipAddress) {
        return geoip_instance()->country($ipAddress);
    });
}
