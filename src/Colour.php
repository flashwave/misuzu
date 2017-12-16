<?php
namespace Misuzu;

class Colour {
    private const INHERIT = 0x40000000;

    private $rawValue = 0;

    public function __get(string $name) {
        switch ($name) {
            case 'raw':
                return $this->rawValue;

            case 'inherit':
                return ($this->rawValue & self::INHERIT) > 0;

            case 'red':
                return $this->rawValue >> 16 & 0xFF;

            case 'green':
                return $this->rawValue >> 8 & 0xFF;

            case 'blue':
                return $this->rawValue & 0xFF;

            case 'hex':
                return dechex_pad($this->red) . dechex_pad($this->green) . dechex_pad($this->blue);
        }

        return null;
    }

    public function __set(string $name, $value): void {
        switch ($name) {
            case 'raw':
                if (!is_int32($value) && !is_uint32($value))
                    break;

                $this->rawValue = $value;
                break;

            case 'inherit':
                if (!is_bool($value))
                    break;

                if ($value)
                    $this->rawValue |= self::INHERIT;
                else
                    $this->rawValue &= ~self::INHERIT;
                break;

            case 'red':
                if (!is_byte($value))
                    break;

                $this->rawValue &= ~0xFF0000;
                $this->rawValue |= $value << 16;
                break;

            case 'green':
                if (!is_byte($value))
                    break;

                $this->rawValue &= ~0xFF00;
                $this->rawValue |= $value << 8;
                break;

            case 'blue':
                if (!is_byte($value))
                    break;

                $this->rawValue &= ~0xFF;
                $this->rawValue |= $value;
                break;

        }
    }

    public function __construct(int $raw) {
        $this->rawValue = $raw;
    }

    public static function fromRGB(int $red, int $green, int $blue): Colour {
        $raw = 0;
        $raw |= ($red & 0xFF) << 16;
        $raw |= ($green & 0xFF) << 8;
        $raw |= $blue & 0xFF;
        return new static($raw);
    }

    public static function fromHex(string $hex): Colour {
        $hex = ltrim(strtolower($hex), '#');
        $hex_length = strlen($hex);

        if ($hex_length === 3)
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        elseif ($hex_length != 6)
            throw new \Exception('Invalid hex colour format! (find a more appropiate exception type)');

        return static::fromRGB(
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2))
        );
    }

    public static function none(): Colour {
        return new static(static::INHERIT);
    }

    public function __toString() {
        return "#{$this->hex}";
    }
}
