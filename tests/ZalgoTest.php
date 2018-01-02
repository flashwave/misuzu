<?php
namespace MisuzuTests;

use PHPUnit\Framework\TestCase;
use Misuzu\Zalgo;

class ZalgoTest extends TestCase
{
    public const TEST_STRING = 'This string will be put through the Zalgo function, and back to a regular string.';

    public function testStrip()
    {
        $this->assertEquals(TEST_STRING, Zalgo::strip(Zalgo::run(TEST_STRING, Zalgo::ZALGO_MODE_MINI)));
        $this->assertEquals(TEST_STRING, Zalgo::strip(Zalgo::run(TEST_STRING, Zalgo::ZALGO_MODE_NORMAL)));
        $this->assertEquals(TEST_STRING, Zalgo::strip(Zalgo::run(TEST_STRING, Zalgo::ZALGO_MODE_MAX)));
    }
}
