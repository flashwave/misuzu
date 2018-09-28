<?php
use Misuzu\Application;

define('MSZ_IP_UNKNOWN', 0);
define('MSZ_IP_V4', 4);
define('MSZ_IP_V6', 6);

define('MSZ_IP_SIZES', [
    MSZ_IP_V4 => 4,
    MSZ_IP_V6 => 16,
]);

function ip_remote_address(string $fallback = '::1'): string
{
    return $_SERVER['REMOTE_ADDR'] ?? $fallback;
}

function ip_country_code(string $ipAddr, string $fallback = 'XX'): string
{
    try {
        return Application::geoip()->country($ipAddr)->country->isoCode ?? $fallback;
    } catch (Exception $e) {
    }

    return $fallback;
}

function ip_detect_string_version(string $address): int
{
    if (filter_var($address, FILTER_VALIDATE_IP) === false) {
        return MSZ_IP_UNKNOWN;
    }

    if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
        return MSZ_IP_V6;
    }

    if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
        return MSZ_IP_V4;
    }

    return MSZ_IP_UNKNOWN;
}

function ip_detect_raw_version(string $raw): int
{
    $rawLength = strlen($raw);

    foreach (MSZ_IP_SIZES as $version => $length) {
        if ($rawLength === $length) {
            return $version;
        }
    }

    return MSZ_IP_UNKNOWN;
}
