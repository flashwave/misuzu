<?php
namespace Misuzu\Net;

use Exception;
use InvalidArgumentException;
use GeoIp2\Exception\AddressNotFoundException;

final class IPAddress {
    public const VERSION_UNKNOWN = 0;
    public const VERSION_4 = 4;
    public const VERSION_6 = 6;

    private const SIZES = [
        self::VERSION_4 => 4,
        self::VERSION_6 => 16,
    ];

    public const DEFAULT_V4 = '127.1';
    public const DEFAULT_V6 = '::1';

    public static function remote(string $fallback = self::DEFAULT_V6): string {
        return $_SERVER['REMOTE_ADDR'] ?? $fallback;
    }

    public static function country(string $address, string $fallback = 'XX'): string {
        try {
            return GeoIP::resolveCountry($address)->country->isoCode ?? $fallback;
        } catch(AddressNotFoundException $e) {
            return $fallback;
        }
    }

    public static function rawWidth(int $version): int {
        return isset(self::SIZES[$version]) ? self::SIZES[$version] : 0;
    }

    public static function detectStringVersion(string $address): int {
        if(filter_var($address, FILTER_VALIDATE_IP) !== false) {
            if(filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false)
                return self::VERSION_6;

            if(filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false)
                return self::VERSION_4;
        }

        return self::VERSION_UNKNOWN;
    }

    public static function detectRawVersion(string $address): int {
        $addressLength = strlen($address);

        foreach(self::SIZES as $version => $length) {
            if($length === $addressLength)
                return $version;
        }

        return self::VERSION_UNKNOWN;
    }

    public static function cidrToRaw(string $cidr): ?array {
        if(strpos($cidr, '/') !== false) {
            [$subnet, $mask] = explode('/', $cidr, 2);
        } else {
            $subnet = $cidr;
        }

        try {
            $subnet = inet_pton($subnet);
        } catch(Exception $ex) {
            return null;
        }

        $mask = empty($mask) ? null : (int)$mask;

        return compact('subnet', 'mask');
    }

    public static function matchCidr(string $address, string $cidr): bool {
        $address = inet_pton($address);
        $cidr = self::cidrToRaw($cidr);
        return self::matchCidrRaw($address, $cidr['subnet'], $cidr['mask']);
    }

    public static function matchCidrRaw(string $address, string $subnet, ?int $mask = null): bool {
        $version = self::detectRawVersion($subnet);

        if($version === self::VERSION_UNKNOWN)
            return false;

        $bits = self::SIZES[$version] * 8;

        if(empty($mask))
            $mask = $bits;

        if($mask < 1 || $mask > $bits || $version !== self::detectRawVersion($subnet))
            return false;

        for($i = 0; $i < ceil($mask / 8); $i++) {
            $byteMask = (0xFF00 >> max(0, min(8, $mask - ($i * 8)))) & 0xFF;
            $addressByte = ord($address[$i]) & $byteMask;
            $subnetByte = ord($subnet[$i]) & $byteMask;

            if($addressByte !== $subnetByte)
                return false;
        }

        return true;
    }
}
