<?php
namespace MisuzuTests;

use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testUsernameValidation()
    {
        $this->assertEquals(user_validate_username('flashwave'), '');
        $this->assertEquals(user_validate_username(' flash '), 'trim');
        $this->assertEquals(user_validate_username('f'), 'short');
        $this->assertEquals(user_validate_username('flaaaaaaaaaaaaaaaash'), 'long');
        $this->assertEquals(user_validate_username('F|@$h'), 'invalid');
        $this->assertEquals(user_validate_username('fl ash_wave'), 'spacing');
        $this->assertEquals(user_validate_username('fl  ash'), 'double-spaces');
    }
}
