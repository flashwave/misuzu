<?php
namespace Misuzu\Net;

use InvalidArgumentException;

/**
 * IP Address object.
 * @package Misuzu\Net
 * @author Julian van de Groep <me@flash.moe>
 */
final class IPAddress
{
    private const FALLBACK_ADDRESS = '::1';

    public const UNKNOWN_VERSION = 0;
    public const V4 = 4;
    public const V6 = 6;

    public const BYTE_COUNT = [
        self::V4 => 4,
        self::V6 => 16,
    ];

    private $ipVersion = self::UNKNOWN_VERSION;
    private $ipRaw = null;

    public function getVersion(): int
    {
        return $this->ipVersion;
    }

    public function getRaw(): string
    {
        return $this->ipRaw;
    }

    public function getString(): string
    {
        return inet_ntop($this->ipRaw);
    }

    public function __construct(int $version, string $rawIp)
    {
        if (!array_key_exists($version, self::BYTE_COUNT)) {
            throw new InvalidArgumentException('Invalid IP version provided.');
        }

        if (strlen($rawIp) !== self::BYTE_COUNT[$version]) {
            throw new InvalidArgumentException('Binary IP was of invalid length.');
        }

        $this->ipVersion = $version;
        $this->ipRaw = $rawIp;
    }

    public function compareTo(IPAddress $other): int
    {
        if ($other->getVersion() !== $this->getVersion()) {
            throw new InvalidArgumentException('Both addresses must be of the same version.');
        }

        $parts_this = array_values(unpack('N*', $this->getRaw()));
        $parts_other = array_values(unpack('N*', $other->getRaw()));
        $size = count($parts_this);

        if ($size !== count($parts_other)) {
            throw new InvalidArgumentException('Addresses varied in length. (if you touched $ipRaw, i will fight you)');
        }

        for ($i = 0; $i < $size; $i++) {
            $result = $parts_other[$i] <=> $parts_this[$i];

            if ($result !== 0) {
                return $result;
            }
        }

        return 0;
    }

    public static function remote(string $fallbackAddress = self::FALLBACK_ADDRESS): IPAddress
    {
        try {
            return self::fromString($_SERVER['REMOTE_ADDR'] ?? $fallbackAddress);
        } catch (InvalidArgumentException $ex) {
            return self::fromString($fallbackAddress);
        }
    }

    public static function fromRaw(string $rawIp): IPAddress
    {
        $version = self::detectVersionFromRaw($rawIp);

        if ($version === self::UNKNOWN_VERSION) {
            throw new InvalidArgumentException('Invalid raw IP address supplied.');
        }

        return new static($version, $rawIp);
    }

    public static function fromString(string $ipAddress): IPAddress
    {
        $version = self::detectVersionFromString($ipAddress);

        if (!array_key_exists($version, self::BYTE_COUNT)) {
            throw new InvalidArgumentException('Invalid IP address supplied.');
        }

        return new static($version, inet_pton($ipAddress));
    }

    public static function detectVersionFromRaw(string $rawIp): int
    {
        $rawLength = strlen($rawIp);

        foreach (self::BYTE_COUNT as $version => $length) {
            if ($rawLength === $length) {
                return $version;
            }
        }

        return self::UNKNOWN_VERSION;
    }

    public static function detectVersionFromString(string $ipAddress): int
    {
        if (filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            return self::UNKNOWN_VERSION;
        }

        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return self::V6;
        }

        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return self::V4;
        }

        return self::UNKNOWN_VERSION;
    }
}
