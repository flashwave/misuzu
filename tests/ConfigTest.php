<?php
namespace MisuzuTests;

use PHPUnit\Framework\TestCase;
use Misuzu\Config\ConfigManager;

define('CONFIG_FILE', sys_get_temp_dir() . '/MisuzuConfigTest' . time() . '.ini');

class ConfigTest extends TestCase
{
    public function testMemoryConfigManager()
    {
        $config = new ConfigManager();
        $this->assertInstanceOf(ConfigManager::class, $config);

        $config->set('TestCat', 'string_val', 'test', 'string');
        $config->set('TestCat', 'int_val', 25, 'int');
        $config->set('TestCat', 'bool_val', true, 'bool');

        $this->assertEquals('test', $config->get('TestCat', 'string_val', 'string'));
        $this->assertEquals(25, $config->get('TestCat', 'int_val', 'int'));
        $this->assertEquals(true, $config->get('TestCat', 'bool_val', 'bool'));
    }

    public function testConfigCreateSet()
    {
        $config = new ConfigManager(CONFIG_FILE);
        $this->assertInstanceOf(ConfigManager::class, $config);

        $config->set('TestCat', 'string_val', 'test', 'string');
        $config->set('TestCat', 'int_val', 25, 'int');
        $config->set('TestCat', 'bool_val', true, 'bool');

        $this->assertEquals('test', $config->get('TestCat', 'string_val', 'string'));
        $this->assertEquals(25, $config->get('TestCat', 'int_val', 'int'));
        $this->assertEquals(true, $config->get('TestCat', 'bool_val', 'bool'));

        $config->save();
    }

    public function testConfigReadGet()
    {
        $config = new ConfigManager(CONFIG_FILE);
        $this->assertInstanceOf(ConfigManager::class, $config);

        $this->assertEquals('test', $config->get('TestCat', 'string_val', 'string'));
        $this->assertEquals(25, $config->get('TestCat', 'int_val', 'int'));
        $this->assertEquals(true, $config->get('TestCat', 'bool_val', 'bool'));
    }

    public function testConfigRemove()
    {
        $config = new ConfigManager(CONFIG_FILE);
        $this->assertInstanceOf(ConfigManager::class, $config);

        $this->assertTrue($config->contains('TestCat', 'string_val'));
        $config->remove('TestCat', 'string_val');

        $config->save();
        $config->load();

        $this->assertFalse($config->contains('TestCat', 'string_val'));

        // tack this onto here, deletes the entire file because we're done with it
        \Misuzu\IO\File::delete(CONFIG_FILE);
    }
}
