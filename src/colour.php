<?php
define('MSZ_COLOUR_INHERIT', 0x40000000);

function colour_create(): int
{
    return 0;
}

function colour_none(): int
{
    return MSZ_COLOUR_INHERIT;
}

function colour_set_inherit(int &$colour): void
{
    $colour |= MSZ_COLOUR_INHERIT;
}

function colour_unset_inherit(int &$colour): void
{
    $colour &= ~MSZ_COLOUR_INHERIT;
}

function colour_get_inherit(int $colour): bool
{
    return ($colour & MSZ_COLOUR_INHERIT) > 0;
}

function colour_get_red(int $colour): int
{
    return ($colour >> 16) & 0xFF;
}

function colour_set_red(int &$colour, int $red): void
{
    $red = $red & 0xFF;
    $colour &= ~0xFF0000;
    $colour |= $red << 16;
}

function colour_get_green(int $colour): int
{
    return ($colour >> 8) & 0xFF;
}

function colour_set_green(int &$colour, int $green): void
{
    $green = $green & 0xFF;
    $colour &= ~0xFF00;
    $colour |= $green << 8;
}

function colour_get_blue(int $colour): int
{
    return $colour & 0xFF;
}

function colour_set_blue(int &$colour, int $blue): void
{
    $blue = $blue & 0xFF;
    $colour &= ~0xFF;
    $colour |= $blue;
}

function colour_get_hex(int $colour, string $format = '#%s'): string
{
    return sprintf(
        $format,
        dechex_pad($colour & 0xFFFFFF, 6)
    );
}

function colour_get_css(int $colour): string
{
    if (colour_get_inherit($colour)) {
        return 'inherit';
    }

    return colour_get_hex($colour);
}

function colour_from_rgb(int &$colour, int $red, int $green, int $blue): bool
{
    colour_set_red($colour, $red);
    colour_set_green($colour, $green);
    colour_set_blue($colour, $blue);
    return true;
}

function colour_from_hex(int &$colour, string $hex): bool
{
    if ($hex[0] === '#') {
        $hex = substr($hex, 1);
    }

    $length = strlen($hex);

    if ($length === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    } elseif ($length !== 6) {
        return false;
    }

    colour_from_rgb(
        $colour,
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2))
    );

    return true;
}
