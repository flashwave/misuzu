<?php
namespace Misuzu;

use InvalidArgumentException;

class Colour
{
    private const INHERIT = 0x40000000;

    private $rawValue = 0;

    public function getRaw(): int
    {
        return $this->rawValue;
    }

    public function setRaw(int $raw): void
    {
        $this->rawValue = $raw;
    }

    public function getInherit(): bool
    {
        return ($this->rawValue & self::INHERIT) > 0;
    }

    public function setInherit(bool $state): void
    {
        if ($state) {
            $this->rawValue |= self::INHERIT;
        } else {
            $this->rawValue &= ~self::INHERIT;
        }
    }

    public function getRed(): int
    {
        return $this->rawValue >> 16 & 0xFF;
    }

    public function setRed(int $red): void
    {
        $red = $red & 0xFF;
        $this->rawValue &= ~0xFF0000;
        $this->rawValue |= $red << 16;
    }

    public function getGreen(): int
    {
        return $this->rawValue >> 8 & 0xFF;
    }

    public function setGreen(int $green): void
    {
        $green = $green & 0xFF;
        $this->rawValue &= ~0xFF00;
        $this->rawValue |= $green << 8;
    }

    public function getBlue(): int
    {
        return $this->rawValue & 0xFF;
    }

    public function setBlue(int $blue): void
    {
        $blue = $blue & 0xFF;
        $this->rawValue &= ~0xFF;
        $this->rawValue |= $blue;
    }

    public function getHex(): string
    {
        return dechex_pad($this->getRed()) . dechex_pad($this->getGreen()) . dechex_pad($this->getBlue());
    }

    public function __construct(?int $raw)
    {
        $this->rawValue = $raw ?? self::INHERIT;
    }

    public static function fromRGB(int $red, int $green, int $blue): Colour
    {
        $raw = 0;
        $raw |= ($red & 0xFF) << 16;
        $raw |= ($green & 0xFF) << 8;
        $raw |= $blue & 0xFF;
        return new static($raw);
    }

    public static function fromHex(string $hex): Colour
    {
        $hex = ltrim(strtolower($hex), '#');
        $hex_length = strlen($hex);

        if ($hex_length === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        } elseif ($hex_length != 6) {
            throw new InvalidArgumentException('Invalid hex colour format!');
        }

        return static::fromRGB(
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2))
        );
    }

    public static function none(): Colour
    {
        return new static(static::INHERIT);
    }

    public function __toString()
    {
        return "#{$this->getHex()}";
    }
}
