<?php
namespace Misuzu\Net;

/**
 * IP functions.
 * @package Misuzu\Net
 * @author Julian van de Groep <me@flash.moe>
 */
class IP
{
    public const V4 = 4;
    public const V6 = 6;

    /**
     * Attempts to get the remote ip address, falls back to IPv6 localhost.
     * @return string
     */
    public static function remote(string $fallback = '::1'): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? $fallback;
    }

    /**
     * Detects IP version.
     * @param string $ip
     * @return int
     */
    public static function version(string $ip): int
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return 0;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return static::V6;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return static::V4;
        }

        return 0;
    }

    /**
     * Converts a printable IP address into an packed binary string.
     * @param string $ip
     * @throws NetInvalidAddressException
     * @return string
     */
    public static function unpack($ip)
    {
        $ipv = static::version($ip);

        if ($ipv === 6) {
            return current(unpack('A16', inet_pton($ip)));
        }

        if ($ipv === 4) {
            return current(unpack('A4', inet_pton($ip)));
        }

        throw new NetInvalidAddressException;
    }

    /**
     * Converts a binary unpacked IP to a printable unpacked IP.
     * @param string $bin
     * @throws NetAddressTypeException
     * @return string
     */
    public static function pack($bin)
    {
        $len = strlen($bin);

        if ($len !== 4 && $len !== 16) {
            throw new NetAddressTypeException;
        }

        return inet_ntop(pack("A{$len}", $bin));
    }
}
