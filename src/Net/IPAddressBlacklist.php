<?php
namespace Misuzu\Net;

use Misuzu\DB;

final class IPAddressBlacklist {
    public static function check(string $address): bool {
        return (bool)DB::prepare("
            SELECT INET6_ATON(:address) AS `target`, (
                SELECT COUNT(*) > 0
                FROM `msz_ip_blacklist`
                WHERE LENGTH(`ip_subnet`) = LENGTH(`target`)
                AND `ip_subnet` & LPAD('', LENGTH(`ip_subnet`), X'FF') << LENGTH(`ip_subnet`) * 8 - `ip_mask`
                    = `target`  & LPAD('', LENGTH(`ip_subnet`), X'FF') << LENGTH(`ip_subnet`) * 8 - `ip_mask`
            )
        ")->bind('address', $address)
          ->fetchColumn(1, false);
    }

    public static function add(string $cidr): bool {
        $raw = IPAddress::cidrToRaw($cidr);

        if(empty($raw))
            return false;

        return self::addRaw($raw['subnet'], $raw['mask']);
    }

    public static function addRaw(string $subnet, ?int $mask = null): bool {
        $version = IPAddress::detectRawVersion($subnet);

        if($version === IPAddress::VERSION_UNKNOWN)
            return false;

        $bits = IPAddress::rawWidth($version) * 8;

        if(empty($mask)) {
            $mask = $bits;
        } elseif($mask < 1 || $mask > $bits) {
            return false;
        }

        return DB::prepare('
            REPLACE INTO `msz_ip_blacklist` (`ip_subnet`, `ip_mask`)
            VALUES (:subnet, :mask)
        ')->bind('subnet', $subnet)
          ->bind('mask', $mask)
          ->execute();
    }

    public static function remove(string $cidr): bool {
        $raw = IPAddress::cidrToRaw($cidr);

        if(empty($raw))
            return false;

        return self::removeRaw($raw['subnet'], $raw['mask']);
    }

    public static function removeRaw(string $subnet, ?int $mask = null): bool {
        return DB::prepare('
            DELETE FROM `msz_ip_blacklist`
            WHERE `ip_subnet` = :subnet
            AND `ip_mask` = :mask
        ')->bind('subnet', $subnet)
          ->bind('mask', $mask)
          ->execute();
    }

    public static function list(): array {
        return DB::query("
            SELECT
                INET6_NTOA(`ip_subnet`) AS `ip_subnet`,
                `ip_mask`,
                LENGTH(`ip_subnet`) AS `ip_bytes`,
                CONCAT(INET6_NTOA(`ip_subnet`), '/', `ip_mask`) as `ip_cidr`
            FROM `msz_ip_blacklist`
        ")->fetchAll();
    }
}
