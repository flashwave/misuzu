<?php
namespace Misuzu\Net;

/**
 * CIDR functions.
 * @package Misuzu\Net
 * @author Julian van de Groep <me@flash.moe>
 */
class CIDR
{
    /**
     * Matches an IP to a CIDR range.
     * @param string $ip
     * @param string $range
     * @return bool
     */
    public static function match($ip, $range)
    {
        [$net, $mask] = explode('/', $range);

        $ipv = IP::version($ip);
        $rangev = IP::version($net);

        if (!$ipv || !$rangev || $ipv !== $rangev) {
            return false;
        }

        switch ($ipv) {
            case IP::V6:
                return static::matchV6($ip, $net, $mask);

            case IP::V4:
                return static::matchV4($ip, $net, $mask);

            default:
                return false;
        }
    }

    /**
     * Matches an IPv4 to a CIDR range.
     * @param string $ip
     * @param string $net
     * @param int $mask
     * @return bool
     */
    private static function matchV4($ip, $net, $mask)
    {
        $ip = ip2long($ip);
        $net = ip2long($net);
        $mask = -1 << (32 - $mask);
        return ($ip & $mask) === $net;
    }

    /**
     * Matches an IPv6 to a CIDR range.
     * @param string $ip
     * @param string $net
     * @param int $mask
     * @return bool
     */
    private static function matchV6($ip, $net, $mask)
    {
        $ip = inet_pton($ip);
        $net = inet_pton($net);
        $mask = static::createV6Mask($mask);
        return ($ip & $mask) === $net;
    }

    /**
     * Converts an IPv6 mask to bytes.
     * @param int $mask
     * @return int
     */
    private static function createV6Mask($mask)
    {
        $range = str_repeat("f", $mask / 4);

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
