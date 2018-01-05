<?php
namespace MisuzuTests;

use PHPUnit\Framework\TestCase;
use Misuzu\Colour;
use InvalidArgumentException;

class ColourTest extends TestCase
{
    public const RED_HEX6 = 67;
    public const GREEN_HEX6 = 45;
    public const BLUE_HEX6 = 23;
    public const SSTR_HEX6 = '#432d17';
    public const STR_HEX6 = '432d17';
    public const RAW_HEX6 = 4402455;

    public const RED_HEX3 = 51;
    public const GREEN_HEX3 = 136;
    public const BLUE_HEX3 = 221;
    public const SSTR_HEX3 = '#38d';
    public const STR_HEX3 = '3388dd';
    public const RAW_HEX3 = 3377373;

    public function testNone()
    {
        $colour = Colour::none();

        $this->assertTrue($colour->inherit);
        $this->assertEquals($colour->raw, 0x40000000);
        $this->assertEquals($colour->red, 0);
        $this->assertEquals($colour->green, 0);
        $this->assertEquals($colour->blue, 0);
        $this->assertEquals($colour->hex, '000000');
    }

    public function testFromRaw()
    {
        $colour = new Colour(static::RAW_HEX6);

        $this->assertEquals($colour->hex, static::STR_HEX6);
        $this->assertEquals($colour->raw, static::RAW_HEX6);
        $this->assertEquals($colour->red, static::RED_HEX6);
        $this->assertEquals($colour->green, static::GREEN_HEX6);
        $this->assertEquals($colour->blue, static::BLUE_HEX6);
        $this->assertFalse($colour->inherit);
    }

    public function testFromRGB()
    {
        $colour = Colour::fromRGB(static::RED_HEX6, static::GREEN_HEX6, static::BLUE_HEX6);

        $this->assertEquals($colour->hex, static::STR_HEX6);
        $this->assertEquals($colour->raw, static::RAW_HEX6);
        $this->assertEquals($colour->red, static::RED_HEX6);
        $this->assertEquals($colour->green, static::GREEN_HEX6);
        $this->assertEquals($colour->blue, static::BLUE_HEX6);
        $this->assertFalse($colour->inherit);
    }

    public function testFromHex()
    {
        $colour = Colour::fromHex(static::SSTR_HEX6);

        $this->assertEquals($colour->hex, static::STR_HEX6);
        $this->assertEquals($colour->raw, static::RAW_HEX6);
        $this->assertEquals($colour->red, static::RED_HEX6);
        $this->assertEquals($colour->green, static::GREEN_HEX6);
        $this->assertEquals($colour->blue, static::BLUE_HEX6);
        $this->assertFalse($colour->inherit);
    }

    public function testFromHex3()
    {
        $colour = Colour::fromHex(static::SSTR_HEX3);

        $this->assertEquals($colour->hex, static::STR_HEX3);
        $this->assertEquals($colour->raw, static::RAW_HEX3);
        $this->assertEquals($colour->red, static::RED_HEX3);
        $this->assertEquals($colour->green, static::GREEN_HEX3);
        $this->assertEquals($colour->blue, static::BLUE_HEX3);
        $this->assertFalse($colour->inherit);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Invalid hex colour format!
     */
    public function testHexException()
    {
        Colour::fromHex('invalid hex code');
    }
}
