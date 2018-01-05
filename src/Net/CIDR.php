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
     * @param string $range
     * @return bool
     */
    public static function match(string $ipAddr, string $range): bool
    {
        [$net, $mask] = explode('/', $range);

        $ipv = IP::version($ipAddr);
        $rangev = IP::version($net);

        if (!$ipv || !$rangev || $ipv !== $rangev) {
            return false;
        }

        switch ($ipv) {
            case IP::V6:
                return static::matchV6($ipAddr, $net, $mask);

            case IP::V4:
                return static::matchV4($ipAddr, $net, $mask);

            default:
                throw new InvalidArgumentException('Invalid IP type.');
        }
    }

    /**
     * Matches an IPv4 to a CIDR range.
     * @param string $ipAddr
     * @param string $net
     * @param int $mask
     * @return bool
     */
    private static function matchV4(string $ipAddr, string $net, int $mask): bool
    {
        $ipAddr = ip2long($ipAddr);
        $net = ip2long($net);
        $mask = -1 << (32 - $mask);
        return ($ipAddr & $mask) === $net;
    }

    /**
     * Matches an IPv6 to a CIDR range.
     * @param string $ipAddr
     * @param string $net
     * @param int $mask
     * @return bool
     */
    private static function matchV6(string $ipAddr, string $net, int $mask): bool
    {
        $ipAddr = inet_pton($ipAddr);
        $net = inet_pton($net);
        $mask = static::createV6Mask($mask);
        return ($ipAddr & $mask) === $net;
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
