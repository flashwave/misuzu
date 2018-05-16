<?php
namespace MisuzuTests;

use PHPUnit\Framework\TestCase;

class ZalgoTest extends TestCase
{
    public const TEST_STRING = 'This string will be put through the Zalgo function, and back to a regular string.';

    public function testStrip()
    {
        $this->assertEquals(
            static::TEST_STRING,
            zalgo_strip(zalgo_run(static::TEST_STRING, MSZ_ZALGO_MODE_MINI))
        );

        $this->assertEquals(
            static::TEST_STRING,
            zalgo_strip(zalgo_run(static::TEST_STRING, MSZ_ZALGO_MODE_NORMAL))
        );

        $this->assertEquals(
            static::TEST_STRING,
            zalgo_strip(zalgo_run(static::TEST_STRING, MSZ_ZALGO_MODE_MAX))
        );
    }
}
