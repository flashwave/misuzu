<?php
namespace Misuzu\Net;

use InvalidArgumentException;

final class IPAddressRange
{
    private $maskAddress;
    private $cidrLength;

    public function getMaskAddress(): IPAddress
    {
        return $this->maskAddress;
    }

    public function getCidrLength(): int
    {
        return $this->cidrLength;
    }

    public function __construct(IPAddress $maskAddress, int $cidrLength)
    {
        if ($cidrLength > IPAddress::BYTE_COUNT[$maskAddress->getVersion()] * 8) {
            throw new InvalidArgumentException('CIDR length is out of range.');
        }

        $this->maskAddress = $maskAddress;
        $this->cidrLength = $cidrLength;
    }

    public function getMaskedString(): string
    {
        return $this->getMaskAddress()->getString() . '/' . $this->getCidrLength();
    }

    public function match(IPAddress $ipAddress, bool $explicitExceptions = false): bool
    {
        if ($ipAddress->getVersion() !== $this->getMaskAddress()->getVersion()) {
            if ($explicitExceptions) {
                throw new InvalidArgumentException('Both addresses must be of the same version.');
            }

            return false;
        }

        $ipParts = array_values(unpack('N*', $ipAddress->getRaw()));
        $maskParts = array_values(unpack('N*', $this->getMaskAddress()->getRaw()));
        $parts = count($ipParts);

        if ($parts !== count($maskParts)) {
            if ($explicitExceptions) {
                throw new InvalidArgumentException('Both addresses must be of the same version (failed 1).');
            }

            return false;
        }

        for ($i = 0; $i < $parts; $i++) {
            $ipParts[$i] = $ipParts[$i] & $maskParts[$i];
        }

        return $this->getMaskAddress()->getRaw() === pack('N*', ...$ipParts);
    }

    public static function fromMaskedString(string $maskedString): IPAddressRange
    {
        if (strpos($maskedString, '/') === false) {
            throw new InvalidArgumentException('Invalid masked string.');
        }

        [$maskedAddress, $cidrLength] = explode('/', $maskedString, 2);
        $maskedAddress = IPAddress::fromString($maskedAddress);
        $cidrLength = (int)$cidrLength;

        return new static($maskedAddress, $cidrLength);
    }

    // very uncertain about this logic in regards to any ip larger than 32 bits
    // if you _do_ know what you're doing, review this and call me an idiot please
    public static function fromRangeString(string $rangeString): IPAddressRange
    {
        if (strpos($rangeString, '-') === false) {
            throw new InvalidArgumentException('Invalid range string.');
        }

        [$rangeStart, $rangeEnd] = explode('-', $rangeString, 2);
        $rangeStart = IPAddress::fromString($rangeStart);
        $rangeEnd = IPAddress::fromString($rangeEnd);

        // implicitly performs a version compare as well, throws an exception if different
        if ($rangeStart->compareTo($rangeEnd) < 1) {
            throw new InvalidArgumentException('Range start was larger (or equal) to the range end.');
        }

        $partsStart = array_values(unpack('N*', $rangeStart->getRaw()));
        $partsEnd = array_values(unpack('N*', $rangeEnd->getRaw()));
        $parts = count($partsStart);

        if ($parts !== count($partsEnd)) {
            throw new InvalidArgumentException('Range start was larger (or equal) to the range end (failed 1).');
        }

        $bits = $parts * 32;
        $mask = array_fill(0, $parts, 0);

        for ($i = 0; $i < $parts; $i++) {
            $diffs = $partsStart[$i] ^ $partsEnd[$i];

            while ($diffs != 0) {
                $diffs >>= 1;
                $bits -= 1;
                $mask[$i] = ($mask[$i] << 1) | 1;
            }

            $mask[$i] = $partsStart[$i] & ~$mask[$i];
        }

        $mask = pack('N*', ...$mask);
        return new static(new IPAddress($rangeStart->getVersion(), $mask), $bits);
    }
}
