<?php
namespace Misuzu;

use InvalidArgumentException;

class Colour {
    private const FLAG_INHERIT = 0x40000000;

    private const READABILITY_THRESHOLD = 186;
    private const LUMINANCE_WEIGHT_RED = .299;
    private const LUMINANCE_WEIGHT_GREEN = .587;
    private const LUMINANCE_WEIGHT_BLUE = .114;

    private int $raw = 0;

    public function __construct(?int $raw = 0) {
        $this->setRaw($raw);
    }

    public static function none(): self {
        return new static(self::FLAG_INHERIT);
    }

    public static function fromRgb(int $red, int $green, int $blue): self {
        return (new static)->setRed($red)->getGreen($green)->setBlue($blue);
    }
    public static function fromHex(string $hex): self {
        return (new static)->setHex($hex);
    }

    public function getRaw(): int {
        return $this->raw;
    }
    public function setRaw(int $raw): self {
        if($raw < 0 || $raw > 0x7FFFFFFF)
            throw new InvalidArgumentException('Invalid raw colour.');
        $this->raw = $raw;
        return $this;
    }

    public function getInherit(): bool {
        return ($this->getRaw() & self::FLAG_INHERIT) > 0;
    }
    public function setInherit(bool $inherit): self {
        $raw = $this->getRaw();

        if($inherit)
            $raw |= self::FLAG_INHERIT;
        else
            $raw &= ~self::FLAG_INHERIT;

        $this->setRaw($raw);

        return $this;
    }

    public function getRed(): int {
        return ($this->getRaw() & 0xFF0000) >> 16;
    }
    public function setRed(int $red): self {
        if($red < 0 || $red > 0xFF)
            throw new InvalidArgumentException('Invalid red value.');

        $raw = $this->getRaw();
        $raw &= ~0xFF0000;
        $raw |= $red << 16;
        $this->setRaw($raw);

        return $this;
    }

    public function getGreen(): int {
        return ($this->getRaw() & 0xFF00) >> 8;
    }
    public function setGreen(int $green): self {
        if($green < 0 || $green > 0xFF)
            throw new InvalidArgumentException('Invalid green value.');

        $raw = $this->getRaw();
        $raw &= ~0xFF00;
        $raw |= $green << 8;
        $this->setRaw($raw);

        return $this;
    }

    public function getBlue(): int {
        return ($this->getRaw() & 0xFF);
    }
    public function setBlue(int $blue): self {
        if($blue < 0 || $blue > 0xFF)
            throw new InvalidArgumentException('Invalid blue value.');

        $raw = $this->getRaw();
        $raw &= ~0xFF;
        $raw |= $blue;
        $this->setRaw($raw);

        return $this;
    }

    public function getLuminance(): int {
        return self::LUMINANCE_WEIGHT_RED   * $this->getRed()
             + self::LUMINANCE_WEIGHT_GREEN * $this->getGreen()
             + self::LUMINANCE_WEIGHT_BLUE  * $this->getBlue();
    }

    public function getHex(): string {
        return str_pad(dechex($this->getRaw() & 0xFFFFFF), 6, '0', STR_PAD_LEFT);
    }
    public function setHex(string $hex): self {
        if($hex[0] === '#')
            $hex = mb_substr($hex, 1);

        if(!ctype_xdigit($hex))
            throw new InvalidArgumentException('Argument contains invalid characters.');

        $length = mb_strlen($hex);

        if($length === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        } elseif($length !== 6) {
            throw new InvalidArgumentException('Argument is not a hex string.');
        }

        return $this->setRaw(hexdec($hex));
    }

    public function getCSS(): string {
        if($this->getInherit())
            return 'inherit';

        return '#' . $this->getHex();
    }

    public static function extractCSSContract(
        string $dark = 'dark', string $light = 'light', bool $inheritIsDark = true
    ): string {
        if($this->getInherit())
            return $inheritIsDark ? $dark : $light;

        return $this->getLuminance($colour) > self::READABILITY_THRESHOLD ? $dark : $light;
    }
}
