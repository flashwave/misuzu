<?php
namespace MisuzuTests;

use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class ColourTest extends TestCase
{
    public const RED_HEX6 = 67;
    public const GREEN_HEX6 = 45;
    public const BLUE_HEX6 = 23;
    public const STR_HEX6 = '#432d17';
    public const RAW_HEX6 = 4402455;

    public const RED_HEX3 = 51;
    public const GREEN_HEX3 = 136;
    public const BLUE_HEX3 = 221;
    public const SSTR_HEX3 = '#38d';
    public const STR_HEX3 = '#3388dd';
    public const RAW_HEX3 = 3377373;

    public function testNone()
    {
        $colour = colour_none();

        $this->assertTrue(colour_get_inherit($colour));
        $this->assertEquals($colour, 0x40000000);
        $this->assertEquals(colour_get_red($colour), 0);
        $this->assertEquals(colour_get_green($colour), 0);
        $this->assertEquals(colour_get_blue($colour), 0);
        $this->assertEquals(colour_get_hex($colour), '#000000');
        $this->assertEquals(colour_get_css($colour), 'inherit');
    }

    public function testNull()
    {
        $colour = colour_create();

        $this->assertFalse(colour_get_inherit($colour));
        $this->assertEquals($colour, 0);
        $this->assertEquals(colour_get_red($colour), 0);
        $this->assertEquals(colour_get_green($colour), 0);
        $this->assertEquals(colour_get_blue($colour), 0);
        $this->assertEquals(colour_get_hex($colour), '#000000');
        $this->assertEquals(colour_get_css($colour), '#000000');
    }

    public function testFromRaw()
    {
        $colour = static::RAW_HEX6;

        $this->assertFalse(colour_get_inherit($colour));
        $this->assertEquals($colour, static::RAW_HEX6);
        $this->assertEquals(colour_get_red($colour), static::RED_HEX6);
        $this->assertEquals(colour_get_green($colour), static::GREEN_HEX6);
        $this->assertEquals(colour_get_blue($colour), static::BLUE_HEX6);
        $this->assertEquals(colour_get_hex($colour), static::STR_HEX6);
        $this->assertEquals(colour_get_css($colour), static::STR_HEX6);
    }

    public function testFromRGB()
    {
        $colour = colour_create();
        colour_from_rgb($colour, static::RED_HEX6, static::GREEN_HEX6, static::BLUE_HEX6);

        $this->assertFalse(colour_get_inherit($colour));
        $this->assertEquals($colour, static::RAW_HEX6);
        $this->assertEquals(colour_get_red($colour), static::RED_HEX6);
        $this->assertEquals(colour_get_green($colour), static::GREEN_HEX6);
        $this->assertEquals(colour_get_blue($colour), static::BLUE_HEX6);
        $this->assertEquals(colour_get_hex($colour), static::STR_HEX6);
        $this->assertEquals(colour_get_css($colour), static::STR_HEX6);
    }

    public function testFromHex()
    {
        $colour = colour_create();
        colour_from_hex($colour, static::STR_HEX6);

        $this->assertFalse(colour_get_inherit($colour));
        $this->assertEquals($colour, static::RAW_HEX6);
        $this->assertEquals(colour_get_red($colour), static::RED_HEX6);
        $this->assertEquals(colour_get_green($colour), static::GREEN_HEX6);
        $this->assertEquals(colour_get_blue($colour), static::BLUE_HEX6);
        $this->assertEquals(colour_get_hex($colour), static::STR_HEX6);
        $this->assertEquals(colour_get_css($colour), static::STR_HEX6);
    }

    public function testFromHex3()
    {
        $colour = colour_create();
        colour_from_hex($colour, static::SSTR_HEX3);

        $this->assertFalse(colour_get_inherit($colour));
        $this->assertEquals($colour, static::RAW_HEX3);
        $this->assertEquals(colour_get_red($colour), static::RED_HEX3);
        $this->assertEquals(colour_get_green($colour), static::GREEN_HEX3);
        $this->assertEquals(colour_get_blue($colour), static::BLUE_HEX3);
        $this->assertEquals(colour_get_hex($colour), static::STR_HEX3);
        $this->assertEquals(colour_get_css($colour), static::STR_HEX3);
    }

    public function testHexException()
    {
        $colour = colour_create();
        $this->assertFalse(colour_from_hex($colour, 'invalid hex code'));
    }
}
