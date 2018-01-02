<?php
namespace MisuzuTests;

use PHPUnit\Framework\TestCase;
use Misuzu\Net\CIDR;

class CIDRTest extends TestCase
{
    public function testIPv4()
    {
        $this->assertTrue(CIDR::match('104.27.135.189', '104.16.0.0/12'));
        $this->assertTrue(CIDR::match('104.27.154.200', '104.16.0.0/12'));
        $this->assertTrue(CIDR::match('104.28.9.4', '104.16.0.0/12'));
    }

    public function testIPv6()
    {
        $this->assertTrue(CIDR::match('2400:cb00:2048:1:0:0:681b:9ac8', '2400:cb00::/32'));
        $this->assertTrue(CIDR::match('2400:cb00:2048:1:0:0:681c:804', '2400:cb00::/32'));
        $this->assertTrue(CIDR::match('2400:cb00:2048:1:0:0:681b:86bd', '2400:cb00::/32'));
        $this->assertTrue(CIDR::match('2400:cb00:2048:1:0:0:681f:5e2a', '2400:cb00::/32'));
    }
}
