<?php
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

function ip_country_code(string $address, string $fallback = 'XX'): string
{
    try {
        return geoip_country($address)->country->isoCode ?? $fallback;
    } catch (Exception $e) {
    }

    return $fallback;
}

function ip_get_string_version(string $address): int
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

function ip_get_raw_version(string $raw): int
{
    $rawLength = strlen($raw);

    foreach (MSZ_IP_SIZES as $version => $length) {
        if ($rawLength === $length) {
            return $version;
        }
    }

    return MSZ_IP_UNKNOWN;
}

function ip_get_raw_width(int $version): int
{
    return MSZ_IP_SIZES[$version] ?? 0;
}

function ip_match_cidr_raw(string $address, string $subnet, int $mask = 0): bool
{
    $version = ip_get_raw_version($subnet);
    $bits = ip_get_raw_width($version) * 8;

    if (empty($mask)) {
        $mask = $bits;
    }

    if ($mask < 1 || $mask > $bits || $version !== ip_get_raw_version($subnet)) {
        return false;
    }

    for ($i = 0; $i < ceil($mask / 8); $i++) {
        $byteMask = (0xFF00 >> min(8, $mask - ($i * 8))) & 0xFF;
        $addressByte = ord($address[$i]) & $byteMask;
        $subnetByte = ord($subnet[$i]) & $byteMask;

        if ($addressByte !== $subnetByte) {
            return false;
        }
    }

    return true;
}

function ip_match_cidr(string $address, string $cidr): bool
{
    if (strpos($cidr, '/') !== false) {
        [$subnet, $mask] = explode('/', $cidr, 2);
    } else {
        $subnet = $cidr;
    }

    $address = inet_pton($address);
    $subnet = inet_pton($subnet);

    return ip_match_cidr_raw($address, $subnet, $mask ?? 0);
}
