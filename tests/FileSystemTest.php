<?php
namespace MisuzuTests;

use PHPUnit\Framework\TestCase;
use Misuzu\IO\Directory;
use Misuzu\IO\File;
use Misuzu\IO\FileStream;

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
        $file = File::open(WORKING_DIR . '/file');
        $this->assertInstanceOf(FileStream::class, $file);
        $file->close();
    }

    public function testWriteFile()
    {
        $file = new FileStream(WORKING_DIR . '/file', FileStream::MODE_TRUNCATE, false);
        $this->assertInstanceOf(FileStream::class, $file);

        $file->write('misuzu');
        $this->assertEquals(6, $file->length);

        $file->close();
    }

    public function testAppendFile()
    {
        $file = new FileStream(WORKING_DIR . '/file', FileStream::MODE_APPEND);
        $this->assertInstanceOf(FileStream::class, $file);

        $file->write(' test');
        $this->assertEquals(11, $file->length);

        $file->close();
    }

    public function testPosition()
    {
        $file = new FileStream(WORKING_DIR . '/file', FileStream::MODE_READ);
        $this->assertInstanceOf(FileStream::class, $file);

        $file->seek(0, FileStream::ORIGIN_BEGIN);
        $this->assertEquals(0, $file->position);

        $file->seek(0, FileStream::ORIGIN_END);
        $this->assertEquals($file->length, $file->position);

        $file->seek(4, FileStream::ORIGIN_BEGIN);
        $this->assertEquals(4, $file->position);

        $file->seek(4, FileStream::ORIGIN_CURRENT);
        $this->assertEquals(8, $file->position);

        $file->close();
    }

    public function testRead()
    {
        $file = new FileStream(WORKING_DIR . '/file', FileStream::MODE_READ);
        $this->assertInstanceOf(FileStream::class, $file);

        $this->assertEquals('misuzu test', $file->read($file->length));

        $file->seek(7, FileStream::ORIGIN_BEGIN);
        $this->assertEquals('test', $file->read(4));

        $file->close();
    }

    public function testChar()
    {
        $file = new FileStream(WORKING_DIR . '/file', FileStream::MODE_READ);
        $this->assertInstanceOf(FileStream::class, $file);

        $file->seek(3, FileStream::ORIGIN_BEGIN);
        $this->assertEquals(ord('u'), $file->readChar());

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
