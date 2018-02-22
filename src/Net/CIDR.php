<?php
namespace Misuzu\Net;

use InvalidArgumentException;

/**
 * CIDR functions.
 * @package Misuzu\Net
 * @author Julian van de Groep <me@flash.moe>
 */
class CIDR
{
    /**
     * Matches an IP to a CIDR range.
     * @param string $ipAddr
     * @param string $network
     * @param int|null $mask
     * @return bool
     */
    public static function match(string $ipAddr, string $network, ?int $mask = null): bool
    {
        if ($mask === null) {
            [$network, $mask] = explode('/', $network);
        }

        if (empty($mask)) {
            throw new InvalidArgumentException('No bitmask supplied.');
        }

        $ipv = IP::version($ipAddr);
        $rangev = IP::version($network);

        if (!$ipv || !$rangev || $ipv !== $rangev) {
            return false;
        }

        switch ($ipv) {
            case IP::V6:
                return static::matchV6($ipAddr, $network, $mask);

            case IP::V4:
                return static::matchV4($ipAddr, $network, $mask);

            default:
                throw new InvalidArgumentException('Invalid IP type.');
        }
    }

    /**
     * Matches an IPv4 to a CIDR range.
     * @param string $ipAddr
     * @param string $network
     * @param int $mask
     * @return bool
     */
    private static function matchV4(string $ipAddr, string $network, int $mask): bool
    {
        $ipAddr = ip2long($ipAddr);
        $network = ip2long($network);
        $mask = -1 << (32 - $mask);
        return ($ipAddr & $mask) === ($network & $mask);
    }

    /**
     * Matches an IPv6 to a CIDR range.
     * @param string $ipAddr
     * @param string $network
     * @param int $mask
     * @return bool
     */
    private static function matchV6(string $ipAddr, string $network, int $mask): bool
    {
        $ipAddr = inet_pton($ipAddr);
        $network = inet_pton($network);
        $mask = static::createV6Mask($mask);
        return ($ipAddr & $mask) === ($network & $mask);
    }

    /**
     * Converts an IPv6 mask to bytes.
     * @param int $mask
     * @return string
     */
    private static function createV6Mask(int $mask): string
    {
        $range = str_repeat('f', $mask / 4);

        switch ($mask % 4) {
            case 1:
                $range .= '8';
                break;

            case 2:
                $range .= 'c';
                break;

            case 3:
                $range .= 'e';
                break;
        }

        $range = str_pad($range, 32, '0');
        $range = pack('H*', $range);

        return $range;
    }
}
