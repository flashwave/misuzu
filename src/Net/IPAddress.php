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
    /**
     * Default IP Address if $_SERVER['REMOTE_ADDR'] is not set.
     */
    private const FALLBACK_ADDRESS = '::1';

    /**
     * Fallback version number.
     */
    public const UNKNOWN_VERSION = 0;

    /**
     * IPv4.
     */
    public const V4 = 4;

    /**
     * IPv6.
     */
    public const V6 = 6;

    /**
     * String lengths of expanded IP addresses.
     */
    public const BYTE_COUNT = [
        self::V4 => 4,
        self::V6 => 16,
    ];

    /**
     * IP address version.
     * @var int
     */
    private $ipVersion = self::UNKNOWN_VERSION;

    /**
     * Raw IP address.
     * @var null|string
     */
    private $ipRaw = null;

    /**
     * @return int
     */
    public function getVersion(): int
    {
        return $this->ipVersion;
    }

    /**
     * @return string
     */
    public function getRaw(): string
    {
        return $this->ipRaw;
    }

    /**
     * @return string
     */
    public function getString(): string
    {
        return inet_ntop($this->ipRaw);
    }

    /**
     * Gets GeoIP country for this IP address.
     * @return string
     */
    public function getCountryCode(): string
    {
        return get_country_code($this->getString());
    }

    /**
     * IPAddress constructor.
     * @param int    $version
     * @param string $rawIp
     */
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

    /**
     * Compares one IP to another.
     * @param IPAddress $other
     * @return int
     * @throws InvalidArgumentException If the versions of the IP mismatch.
     */
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

    /**
     * Gets the remote address.
     * @param string $fallbackAddress
     * @return IPAddress
     */
    public static function remote(string $fallbackAddress = self::FALLBACK_ADDRESS): IPAddress
    {
        try {
            return self::fromString(remote_address($fallbackAddress));
        } catch (InvalidArgumentException $ex) {
            return self::fromString($fallbackAddress);
        }
    }

    /**
     * Creates an IPAddress instance from just a raw IP string.
     * @param string $rawIp
     * @return IPAddress
     */
    public static function fromRaw(string $rawIp): IPAddress
    {
        $version = self::detectVersionFromRaw($rawIp);

        if ($version === self::UNKNOWN_VERSION) {
            throw new InvalidArgumentException('Invalid raw IP address supplied.');
        }

        return new static($version, $rawIp);
    }

    /**
     * Creates an IPAddress instance from a human readable address string.
     * @param string $ipAddress
     * @return IPAddress
     */
    public static function fromString(string $ipAddress): IPAddress
    {
        $version = self::detectVersionFromString($ipAddress);

        if (!array_key_exists($version, self::BYTE_COUNT)) {
            throw new InvalidArgumentException('Invalid IP address supplied.');
        }

        return new static($version, inet_pton($ipAddress));
    }

    /**
     * Detects the version of a raw address string.
     * @param string $rawIp
     * @return int
     */
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

    /**
     * Detects the version of a human readable address string.
     * @param string $ipAddress
     * @return int
     */
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
