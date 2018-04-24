<?php
namespace Misuzu;

use InvalidArgumentException;

/**
 * Class Colour
 * @package Misuzu
 */
class Colour
{
    /**
     * Flag to set when this this should inherit a parent's colour.
     */
    private const INHERIT = 0x40000000;

    /**
     * Raw colour value, 32-bit integer (although only 25 bits are used).
     * @var int
     */
    private $rawValue = 0;

    /**
     * Gets the raw colour value.
     * @return int
     */
    public function getRaw(): int
    {
        return $this->rawValue;
    }

    /**
     * Sets a raw colour value.
     * @param int $raw
     */
    public function setRaw(int $raw): void
    {
        $this->rawValue = $raw & 0xFFFFFFFF;
    }

    /**
     * Gets whether the inheritance flag is set.
     * @return bool
     */
    public function getInherit(): bool
    {
        return ($this->rawValue & self::INHERIT) > 0;
    }

    /**
     * Toggles the inheritance flag.
     * @param bool $state
     */
    public function setInherit(bool $state): void
    {
        if ($state) {
            $this->rawValue |= self::INHERIT;
        } else {
            $this->rawValue &= ~self::INHERIT;
        }
    }

    /**
     * Gets the red colour byte.
     * @return int
     */
    public function getRed(): int
    {
        return $this->rawValue >> 16 & 0xFF;
    }

    /**
     * Sets the red colour byte.
     * @param int $red
     */
    public function setRed(int $red): void
    {
        $red = $red & 0xFF;
        $this->rawValue &= ~0xFF0000;
        $this->rawValue |= $red << 16;
    }

    /**
     * Gets the green colour byte.
     * @return int
     */
    public function getGreen(): int
    {
        return $this->rawValue >> 8 & 0xFF;
    }

    /**
     * Sets the green colour byte.
     * @param int $green
     */
    public function setGreen(int $green): void
    {
        $green = $green & 0xFF;
        $this->rawValue &= ~0xFF00;
        $this->rawValue |= $green << 8;
    }

    /**
     * Gets the blue colour byte.
     * @return int
     */
    public function getBlue(): int
    {
        return $this->rawValue & 0xFF;
    }

    /**
     * Sets the blue colour byte.
     * @param int $blue
     */
    public function setBlue(int $blue): void
    {
        $blue = $blue & 0xFF;
        $this->rawValue &= ~0xFF;
        $this->rawValue |= $blue;
    }

    /**
     * Gets the hexidecimal value for this colour, without # prefix.
     * @return string
     */
    public function getHex(): string
    {
        return dechex_pad($this->getRaw() & 0xFFFFFF, 6);
    }

    /**
     * Colour constructor.
     * @param int|null $raw
     */
    public function __construct(?int $raw)
    {
        $this->rawValue = $raw ?? self::INHERIT;
    }

    /**
     * @param int $red
     * @param int $green
     * @param int $blue
     * @return Colour
     */
    public static function fromRGB(int $red, int $green, int $blue): Colour
    {
        $raw = 0;
        $raw |= ($red & 0xFF) << 16;
        $raw |= ($green & 0xFF) << 8;
        $raw |= $blue & 0xFF;
        return new static($raw);
    }

    /**
     * @param string $hex
     * @return Colour
     */
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

    /**
     * @return Colour
     */
    public static function none(): Colour
    {
        return new static(static::INHERIT);
    }

    /**
     * Gets the hexidecimal value for this colour, with # prefix.
     * @return string
     */
    public function __toString()
    {
        return "#{$this->getHex()}";
    }
}
