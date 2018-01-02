<?php
namespace MisuzuTests;

use PHPUnit\Framework\TestCase;
use Misuzu\IO\Directory;
use Misuzu\IO\File;

define('WORKING_DIR', sys_get_temp_dir() . '/MisuzuFileSystemTest' . time());

class FileSystemTest extends TestCase
{
    public function testSlashFix()
    {
        $right_slash = DIRECTORY_SEPARATOR;
        $wrong_slash = DIRECTORY_SEPARATOR == '/' ? '\\' : '/';

        $this->assertEquals(
            Directory::fixSlashes("test{$wrong_slash}dir{$wrong_slash}with{$wrong_slash}wrong{$wrong_slash}slashes"),
            "test{$right_slash}dir{$right_slash}with{$right_slash}wrong{$right_slash}slashes"
        );
    }

    public function testExists()
    {
        $this->assertTrue(Directory::exists(sys_get_temp_dir()));
        $this->assertFalse(Directory::exists(WORKING_DIR));
    }

    public function testCreateDir()
    {
        $directory = Directory::create(WORKING_DIR);
        $this->assertInstanceOf(Directory::class, $directory);
    }

    public function testCreateFile()
    {
        $file = new File(WORKING_DIR . '/file', File::CREATE_READ_WRITE);
        $this->assertInstanceOf(File::class, $file);
        $file->close();
    }

    public function testWriteFile()
    {
        $file = new File(WORKING_DIR . '/file', File::OPEN_WRITE);
        $this->assertInstanceOf(File::class, $file);

        $file->write('misuzu');
        $this->assertEquals(6, $file->size);

        $file->close();
    }

    public function testAppendFile()
    {
        $file = new File(WORKING_DIR . '/file', File::OPEN_WRITE);
        $this->assertInstanceOf(File::class, $file);

        $file->append(' test');
        $this->assertEquals(11, $file->size);

        $file->close();
    }

    public function testPosition()
    {
        $file = new File(WORKING_DIR . '/file', File::OPEN_READ);
        $this->assertInstanceOf(File::class, $file);

        $file->start();
        $this->assertEquals(0, $file->position);

        $file->end();
        $this->assertEquals($file->size, $file->position);

        $file->position(4);
        $this->assertEquals(4, $file->position);

        $file->position(4, true);
        $this->assertEquals(8, $file->position);

        $file->close();
    }

    public function testRead()
    {
        $file = new File(WORKING_DIR . '/file', File::OPEN_READ);
        $this->assertInstanceOf(File::class, $file);

        $this->assertEquals('misuzu test', $file->read());

        $file->position(7);
        $this->assertEquals('test', $file->read());

        $file->close();
    }

    public function testFind()
    {
        $file = new File(WORKING_DIR . '/file', File::OPEN_READ);
        $this->assertInstanceOf(File::class, $file);

        $this->assertEquals(7, $file->find('test'));

        $file->close();
    }

    public function testChar()
    {
        $file = new File(WORKING_DIR . '/file', File::OPEN_READ);
        $this->assertInstanceOf(File::class, $file);

        $file->position(3);
        $this->assertEquals('s', $file->char());

        $file->close();
    }

    public function testDirectoryFiles()
    {
        $dir = new Directory(WORKING_DIR);
        $this->assertEquals([realpath(WORKING_DIR . DIRECTORY_SEPARATOR . 'file')], $dir->files());
    }

    public function testDelete()
    {
        File::delete(WORKING_DIR . '/file');
        $this->assertFalse(File::exists(WORKING_DIR . '/file'));

        Directory::delete(WORKING_DIR);
        $this->assertFalse(Directory::exists(WORKING_DIR));
    }
}
