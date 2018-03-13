<?php
namespace MisuzuTests;

use PHPUnit\Framework\TestCase;
use Misuzu\IO\Directory;
use Misuzu\IO\File;
use Misuzu\IO\FileStream;

class FileSystemTest extends TestCase
{
    protected $workingDirectory;

    protected function setUp()
    {
        $this->workingDirectory = sys_get_temp_dir() . '/MisuzuFileSystemTest' . time();
    }

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
        $this->assertFalse(Directory::exists($this->workingDirectory));
    }

    public function testCreateDir()
    {
        $directory = Directory::create($this->workingDirectory);
        $this->assertInstanceOf(Directory::class, $directory);
    }

    public function testCreateFile()
    {
        $file = File::open($this->workingDirectory . '/file');
        $this->assertInstanceOf(FileStream::class, $file);
        $file->close();
    }

    public function testWriteFile()
    {
        $file = new FileStream($this->workingDirectory . '/file', FileStream::MODE_TRUNCATE);
        $this->assertInstanceOf(FileStream::class, $file);

        $file->write('mis');
        $file->write('uzu');
        $this->assertEquals(6, $file->length);

        $file->close();
    }

    public function testAppendFile()
    {
        $file = new FileStream($this->workingDirectory . '/file', FileStream::MODE_APPEND);
        $this->assertInstanceOf(FileStream::class, $file);

        $file->write(' test');
        $this->assertEquals(11, $file->length);

        $file->close();
    }

    public function testPosition()
    {
        $file = new FileStream($this->workingDirectory . '/file', FileStream::MODE_READ);
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
        $file = new FileStream($this->workingDirectory . '/file', FileStream::MODE_READ);
        $this->assertInstanceOf(FileStream::class, $file);

        $this->assertEquals('misuzu test', $file->read($file->length));

        $file->seek(7, FileStream::ORIGIN_BEGIN);
        $this->assertEquals('test', $file->read(4));

        $file->close();
    }

    public function testChar()
    {
        $file = new FileStream($this->workingDirectory . '/file', FileStream::MODE_READ);
        $this->assertInstanceOf(FileStream::class, $file);

        $file->seek(3, FileStream::ORIGIN_BEGIN);
        $this->assertEquals(ord('u'), $file->readChar());

        $file->close();
    }

    public function testDirectoryFiles()
    {
        $dir = new Directory($this->workingDirectory);
        $this->assertEquals([realpath($this->workingDirectory . DIRECTORY_SEPARATOR . 'file')], $dir->files());
    }

    public function testDelete()
    {
        File::delete($this->workingDirectory . '/file');
        $this->assertFalse(File::exists($this->workingDirectory . '/file'));

        Directory::delete($this->workingDirectory);
        $this->assertFalse(Directory::exists($this->workingDirectory));
    }
}
