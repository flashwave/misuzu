<?php
namespace MisuzuTests;

use PHPUnit\Framework\TestCase;
use Misuzu\Net\IPAddress;
use Misuzu\Net\IPAddressRange;

class IPAddressTest extends TestCase
{
    public function testVersion()
    {
        $this->assertEquals(IPAddress::V4, IPAddress::detectVersionFromString('127.0.0.1'));
        $this->assertEquals(IPAddress::V4, IPAddress::detectVersionFromRaw(hex2bin('7f000001')));

        $this->assertEquals(IPAddress::V4, IPAddress::detectVersionFromString('104.27.135.189'));
        $this->assertEquals(IPAddress::V4, IPAddress::detectVersionFromRaw(hex2bin('681b87bd')));

        $this->assertEquals(IPAddress::V4, IPAddress::detectVersionFromString('104.27.154.200'));
        $this->assertEquals(IPAddress::V4, IPAddress::detectVersionFromRaw(hex2bin('681b9ac8')));

        $this->assertEquals(IPAddress::V4, IPAddress::detectVersionFromString('104.28.9.4'));
        $this->assertEquals(IPAddress::V4, IPAddress::detectVersionFromRaw(hex2bin('681c0904')));

        $this->assertEquals(IPAddress::V6, IPAddress::detectVersionFromString('::1'));
        $this->assertEquals(
            IPAddress::V6,
            IPAddress::detectVersionFromRaw(hex2bin('00000000000000000000000000000001'))
        );

        $this->assertEquals(IPAddress::V6, IPAddress::detectVersionFromString('2400:cb00:2048:1:0:0:681b:9ac8'));
        $this->assertEquals(
            IPAddress::V6,
            IPAddress::detectVersionFromRaw(hex2bin('2400cb002048000100000000681b9ac8'))
        );

        $this->assertEquals(IPAddress::V6, IPAddress::detectVersionFromString('2400:cb00:2048:1:0:0:681c:804'));
        $this->assertEquals(
            IPAddress::V6,
            IPAddress::detectVersionFromRaw(hex2bin('2400cb002048000100000000681c0804'))
        );

        $this->assertEquals(IPAddress::V6, IPAddress::detectVersionFromString('2400:cb00:2048:1:0:0:681b:86bd'));
        $this->assertEquals(
            IPAddress::V6,
            IPAddress::detectVersionFromRaw(hex2bin('2400cb002048000100000000681b86bd'))
        );

        $this->assertEquals(IPAddress::V6, IPAddress::detectVersionFromString('2400:cb00:2048:1:0:0:681f:5e2a'));
        $this->assertEquals(
            IPAddress::V6,
            IPAddress::detectVersionFromRaw(hex2bin('2400cb002048000100000000681f5e2a'))
        );

        $this->assertEquals(IPAddress::UNKNOWN_VERSION, IPAddress::detectVersionFromString('not an ip address'));
        $this->assertEquals(IPAddress::UNKNOWN_VERSION, IPAddress::detectVersionFromString('256.256.256.256'));
        $this->assertEquals(IPAddress::UNKNOWN_VERSION, IPAddress::detectVersionFromRaw('invalid'));
    }

    public function testString()
    {
        $this->assertEquals(hex2bin('7f000001'), IPAddress::fromString('127.0.0.1')->getRaw());
        $this->assertEquals(hex2bin('681b87bd'), IPAddress::fromString('104.27.135.189')->getRaw());
        $this->assertEquals(hex2bin('681b9ac8'), IPAddress::fromString('104.27.154.200')->getRaw());
        $this->assertEquals(hex2bin('681c0904'), IPAddress::fromString('104.28.9.4')->getRaw());

        $this->assertEquals(
            hex2bin('00000000000000000000000000000001'),
            IPAddress::fromString('::1')->getRaw()
        );
        $this->assertEquals(
            hex2bin('2400cb002048000100000000681b9ac8'),
            IPAddress::fromString('2400:cb00:2048:1:0:0:681b:9ac8')->getRaw()
        );
        $this->assertEquals(
            hex2bin('2400cb002048000100000000681c0804'),
            IPAddress::fromString('2400:cb00:2048:1:0:0:681c:804')->getRaw()
        );
        $this->assertEquals(
            hex2bin('2400cb002048000100000000681b86bd'),
            IPAddress::fromString('2400:cb00:2048:1:0:0:681b:86bd')->getRaw()
        );
        $this->assertEquals(
            hex2bin('2400cb002048000100000000681f5e2a'),
            IPAddress::fromString('2400:cb00:2048:1:0:0:681f:5e2a')->getRaw()
        );
    }

    public function testRaw()
    {
        $this->assertEquals('127.0.0.1', IPAddress::fromRaw(hex2bin('7f000001'))->getString());
        $this->assertEquals('104.27.135.189', IPAddress::fromRaw(hex2bin('681b87bd'))->getString());
        $this->assertEquals('104.27.154.200', IPAddress::fromRaw(hex2bin('681b9ac8'))->getString());
        $this->assertEquals('104.28.9.4', IPAddress::fromRaw(hex2bin('681c0904'))->getString());

        $this->assertEquals(
            '::1',
            IPAddress::fromRaw(hex2bin('00000000000000000000000000000001'))->getString()
        );
        $this->assertEquals(
            IPAddress::fromRaw(hex2bin('2400cb002048000100000000681b9ac8'))->getString(),
            '2400:cb00:2048:1::681b:9ac8'
        );
        $this->assertEquals(
            '2400:cb00:2048:1::681c:804',
            IPAddress::fromRaw(hex2bin('2400cb002048000100000000681c0804'))->getString()
        );
        $this->assertEquals(
            '2400:cb00:2048:1::681b:86bd',
            IPAddress::fromRaw(hex2bin('2400cb002048000100000000681b86bd'))->getString()
        );
        $this->assertEquals(
            '2400:cb00:2048:1::681f:5e2a',
            IPAddress::fromRaw(hex2bin('2400cb002048000100000000681f5e2a'))->getString()
        );
    }

    public function testCompare()
    {
        $v4_start = IPAddress::fromString('117.0.0.255');
        $v4_end = IPAddress::fromString('127.0.0.255');
        $v6_start = IPAddress::fromString('::1');
        $v6_end = IPAddress::fromString('::FFFF');

        $this->assertEquals(1, $v4_start->compareTo($v4_end));
        $this->assertEquals(-1, $v4_end->compareTo($v4_start));
        $this->assertEquals(0, $v4_start->compareTo($v4_start));
        $this->assertEquals(0, $v4_end->compareTo($v4_end));

        $this->assertEquals(1, $v6_start->compareTo($v6_end));
        $this->assertEquals(-1, $v6_end->compareTo($v6_start));
        $this->assertEquals(0, $v6_start->compareTo($v6_start));
        $this->assertEquals(0, $v6_end->compareTo($v6_end));
    }

    public function testMaskedRange()
    {
        $range_v4 = IPAddressRange::fromMaskedString('127.0.0.1/8');
        $this->assertEquals('127.0.0.1', $range_v4->getMaskAddress()->getString());
        $this->assertEquals(8, $range_v4->getCidrLength());
        $this->assertEquals('127.0.0.1/8', $range_v4->getMaskedString());

        $range_v6 = IPAddressRange::fromMaskedString('::1/16');
        $this->assertEquals('::1', $range_v6->getMaskAddress()->getString());
        $this->assertEquals(16, $range_v6->getCidrLength());
        $this->assertEquals('::1/16', $range_v6->getMaskedString());
    }

    // excellent naming
    public function testRangedRange()
    {
        $range_v4 = IPAddressRange::fromRangeString('255.255.255.248-255.255.255.255');
        $this->assertEquals('255.255.255.248', $range_v4->getMaskAddress()->getString());
        $this->assertEquals(29, $range_v4->getCidrLength());

        $range_v6 = IPAddressRange::fromRangeString('2400:cb00:2048:1::681b:86bd-2400:cb00:2048:1::681f:5e2a');
        $this->assertEquals('2400:cb00:2048:1::6818:0', $range_v6->getMaskAddress()->getString());
        $this->assertEquals(109, $range_v6->getCidrLength());
    }

    public function testMatchRange()
    {
        $range_v4 = new IPAddressRange(IPAddress::fromString('108.162.192.0'), 18);
        $this->assertTrue($range_v4->match(IPAddress::fromString('108.162.255.255')));
        $this->assertFalse($range_v4->match(IPAddress::fromString('127.0.0.1')));

        $range_v6 = new IPAddressRange(IPAddress::fromString('2a06:98c0::'), 29);
        $this->assertTrue($range_v6->match(IPAddress::fromString('2a06:98c7:7f:43:645:ab:cd:2525')));
        $this->assertFalse($range_v6->match(IPAddress::fromString('::1')));
    }
}
