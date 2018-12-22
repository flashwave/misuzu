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

function ip_country_code(string $ipAddr, string $fallback = 'XX'): string
{
    try {
        return geoip_country($ipAddr)->country->isoCode ?? $fallback;
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

function ip_detect_raw_version(string $raw, bool $returnWidth = false): int
{
    $rawLength = strlen($raw);

    foreach (MSZ_IP_SIZES as $version => $length) {
        if ($rawLength === $length) {
            return $returnWidth ? $length : $version;
        }
    }

    return MSZ_IP_UNKNOWN;
}

function ip_get_raw_width(int $version): int
{
    return MSZ_IP_SIZES[$version] ?? 0;
}

// Takes 1.2.3.4/n notation, returns subnet mask in raw bytes
function ip_cidr_to_mask(string $ipRange): string
{
    [$address, $bits] = explode('/', $ipRange, 2);

    $address = inet_pton($address);
    $width = ip_detect_raw_version($address, true) * 8;

    if ($bits < 1 || $bits > $width) {
        return str_repeat(chr(0), $width);
    }

    $mask = '';

    for ($i = 0; $i < floor($width / 8); $i++) {
        $addressByte = ord($address[$i]);
        $maskByte = 0;

        for ($j = 0; $j < 8; $j++) {
            $offset = (8 * $i) + $j;
            $bit = 0x80 >> $j;

            if ($offset < $bits && ($addressByte & $bit) > 0) {
                $maskByte |= $bit;
            } else {
                $maskByte &= ~$bit;
            }
        }

        $mask .= chr($maskByte);
    }

    return $mask;
}

// Takes a RAW IP and a RAW MASK
function ip_match_mask(string $ipAddress, string $mask): int
{
    $width = strlen($mask);
    $result = false;

    if (strlen($ipAddress) !== $width) {
        return $result;
    }

    for ($i = 0; $i < $width; $i++) {
        $result &= ($ipAddress[$i] & ~$mask[$i]) === 0;
    }

    return $result;
}
