<?php
namespace MisuzuTests;

use PHPUnit\Framework\TestCase;
use Misuzu\Net\IP;

class IPTest extends TestCase
{
    public function testVersion()
    {
        $this->assertEquals(IP::version('127.0.0.1'), IP::V4);
        $this->assertEquals(IP::version('104.27.135.189'), IP::V4);
        $this->assertEquals(IP::version('104.27.154.200'), IP::V4);
        $this->assertEquals(IP::version('104.28.9.4'), IP::V4);

        $this->assertEquals(IP::version('::1'), IP::V6);
        $this->assertEquals(IP::version('2400:cb00:2048:1:0:0:681b:9ac8'), IP::V6);
        $this->assertEquals(IP::version('2400:cb00:2048:1:0:0:681c:804'), IP::V6);
        $this->assertEquals(IP::version('2400:cb00:2048:1:0:0:681b:86bd'), IP::V6);
        $this->assertEquals(IP::version('2400:cb00:2048:1:0:0:681f:5e2a'), IP::V6);

        $this->assertEquals(IP::version('not an ip address'), 0);
        $this->assertEquals(IP::version('256.256.256.256'), 0);
    }

    public function testUnpack()
    {
        $this->assertEquals(IP::unpack('127.0.0.1'), "\x7f\x00\x00\x01");
        $this->assertEquals(IP::unpack('104.27.135.189'), "\x68\x1b\x87\xbd");
        $this->assertEquals(IP::unpack('104.27.154.200'), "\x68\x1b\x9a\xc8");
        $this->assertEquals(IP::unpack('104.28.9.4'), "\x68\x1c\x09\x04");

        $this->assertEquals(IP::unpack('::1'), "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x01");
        $this->assertEquals(
            IP::unpack('2400:cb00:2048:1:0:0:681b:9ac8'),
            "\x24\x00\xcb\x00\x20\x48\x00\x01\x00\x00\x00\x00\x68\x1b\x9a\xc8"
        );
        $this->assertEquals(
            IP::unpack('2400:cb00:2048:1:0:0:681c:804'),
            "\x24\x00\xcb\x00\x20\x48\x00\x01\x00\x00\x00\x00\x68\x1c\x08\x04"
        );
        $this->assertEquals(
            IP::unpack('2400:cb00:2048:1:0:0:681b:86bd'),
            "\x24\x00\xcb\x00\x20\x48\x00\x01\x00\x00\x00\x00\x68\x1b\x86\xbd"
        );
        $this->assertEquals(
            IP::unpack('2400:cb00:2048:1:0:0:681f:5e2a'),
            "\x24\x00\xcb\x00\x20\x48\x00\x01\x00\x00\x00\x00\x68\x1f\x5e\x2a"
        );
    }

    public function testPack()
    {
        $this->assertEquals(IP::pack("\x7f\x00\x00\x01"), '127.0.0.1');
        $this->assertEquals(IP::pack("\x68\x1b\x87\xbd"), '104.27.135.189');
        $this->assertEquals(IP::pack("\x68\x1b\x9a\xc8"), '104.27.154.200');
        $this->assertEquals(IP::pack("\x68\x1c\x09\x04"), '104.28.9.4');

        $this->assertEquals(IP::pack("\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x01"), '::1');
        $this->assertEquals(
            IP::pack("\x24\x00\xcb\x00\x20\x48\x00\x01\x00\x00\x00\x00\x68\x1b\x9a\xc8"),
            '2400:cb00:2048:1::681b:9ac8'
        );
        $this->assertEquals(
            IP::pack("\x24\x00\xcb\x00\x20\x48\x00\x01\x00\x00\x00\x00\x68\x1c\x08\x04"),
            '2400:cb00:2048:1::681c:804'
        );
        $this->assertEquals(
            IP::pack("\x24\x00\xcb\x00\x20\x48\x00\x01\x00\x00\x00\x00\x68\x1b\x86\xbd"),
            '2400:cb00:2048:1::681b:86bd'
        );
        $this->assertEquals(
            IP::pack("\x24\x00\xcb\x00\x20\x48\x00\x01\x00\x00\x00\x00\x68\x1f\x5e\x2a"),
            '2400:cb00:2048:1::681f:5e2a'
        );
    }
}
