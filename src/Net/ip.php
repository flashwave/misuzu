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

function ip_match_cidr_raw(string $address, string $subnet, ?int $mask = null): bool
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
        $byteMask = (0xFF00 >> max(0, min(8, $mask - ($i * 8)))) & 0xFF;
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
    $address = inet_pton($address);
    [$subnet, $mask] = ['', 0];
    extract(ip_cidr_to_raw($cidr));

    return ip_match_cidr_raw($address, $subnet, $mask);
}

function ip_cidr_to_raw(string $cidr): ?array
{
    if (strpos($cidr, '/') !== false) {
        [$subnet, $mask] = explode('/', $cidr, 2);
    } else {
        $subnet = $cidr;
    }

    try {
        $subnet = inet_pton($subnet);
    } catch (Exception $ex) {
        return null;
    }

    $mask = empty($mask) ? null : (int)$mask;

    return compact('subnet', 'mask');
}

function ip_blacklist_check(string $address): bool
{
    $checkBlacklist = db_prepare("
        SELECT COUNT(*) > 0
        FROM `msz_ip_blacklist`
        WHERE LENGTH(`ip_subnet`) = LENGTH(INET6_ATON(:ip1))
        AND `ip_subnet`         & LPAD('', LENGTH(`ip_subnet`), X'FF') << LENGTH(`ip_subnet`) * 8 - `ip_mask`
            = INET6_ATON(:ip2)  & LPAD('', LENGTH(`ip_subnet`), X'FF') << LENGTH(`ip_subnet`) * 8 - `ip_mask`
    ");
    $checkBlacklist->bindValue('ip1', $address);
    $checkBlacklist->bindValue('ip2', $address);
    return (bool)($checkBlacklist->execute() ? $checkBlacklist->fetchColumn() : false);
}

function ip_blacklist_add_raw(string $subnet, ?int $mask = null): bool
{
    $version = ip_get_raw_version($subnet);

    if ($version === 0) {
        return false;
    }

    $bits = ip_get_raw_width($version) * 8;

    if (empty($mask)) {
        $mask = $bits;
    } elseif ($mask < 1 || $mask > $bits) {
        return false;
    }

    $addBlacklist = db_prepare('
        REPLACE INTO `msz_ip_blacklist`
            (`ip_subnet`, `ip_mask`)
        VALUES
            (:subnet, :mask)
    ');
    $addBlacklist->bindValue('subnet', $subnet);
    $addBlacklist->bindValue('mask', $mask);
    return $addBlacklist->execute();
}

function ip_blacklist_add(string $cidr): bool
{
    $raw = ip_cidr_to_raw($cidr);

    if (empty($raw)) {
        return false;
    }

    return ip_blacklist_add_raw($raw['subnet'], $raw['mask']);
}

function ip_blacklist_remove_raw(string $subnet, ?int $mask = null): bool
{
    $removeBlacklist = db_prepare('
        DELETE FROM `msz_ip_blacklist`
        WHERE `ip_subnet` = :subnet
        AND `ip_mask` = :mask
    ');
    $removeBlacklist->bindValue('subnet', $subnet);
    $removeBlacklist->bindValue('mask', $mask);
    return $removeBlacklist->execute();
}

function ip_blacklist_remove(string $cidr): bool
{
    $raw = ip_cidr_to_raw($cidr);

    if (empty($raw)) {
        return false;
    }

    return ip_blacklist_remove_raw($raw['subnet'], $raw['mask']);
}

function ip_blacklist_list(): array
{
    $getBlacklist = db_query("
        SELECT
            INET6_NTOA(`ip_subnet`) AS `ip_subnet`,
            `ip_mask`,
            LENGTH(`ip_subnet`) AS `ip_bytes`,
            CONCAT(INET6_NTOA(`ip_subnet`), '/', `ip_mask`) as `ip_cidr`
        FROM `msz_ip_blacklist`
    ");
    return db_fetch_all($getBlacklist);
}
