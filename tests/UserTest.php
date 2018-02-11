<?php
namespace MisuzuTests;

use PHPUnit\Framework\TestCase;
use Misuzu\Users\User;

class UserTest extends TestCase
{
    public function testUsernameValidation()
    {
        $this->assertEquals(User::validateUsername('flashwave'), '');
        $this->assertEquals(User::validateUsername(' flash '), 'trim');
        $this->assertEquals(User::validateUsername('f'), 'short');
        $this->assertEquals(User::validateUsername('flaaaaaaaaaaaaaaaash'), 'long');
        $this->assertEquals(User::validateUsername('F|@$h'), 'invalid');
        $this->assertEquals(User::validateUsername('fl ash_wave'), 'spacing');
    }
}
