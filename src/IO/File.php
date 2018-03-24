<?php
namespace Misuzu\IO;

use Exception;

/**
 * Static file meta functions.
 * @package Misuzu\IO
 * @author Julian van de Groep <me@flash.moe>
 */
class File
{
    public static function open(string $filename): FileStream
    {
        return new FileStream($filename, FileStream::MODE_READ_WRITE, true);
    }

    public static function readToEnd(string $filename, bool $lock = false): string
    {
        $output = '';

        try {
            $file = new FileStream($filename, FileStream::MODE_READ, $lock);
            $output = $file->read($file->getLength());
            $file->close();
        } catch (Exception $ex) {
        }

        return $output;
    }

    public static function writeAll(string $filename, string $data): void
    {
        $file = new FileStream($filename, FileStream::MODE_TRUNCATE, true);
        $file->write($data);
        $file->close();
    }

    /**
     * Creates an instance of a temporary file.
     * @param string $prefix
     * @return FileStream
     */
    public static function temp(string $prefix = 'Misuzu'): FileStream
    {
        return static::open(tempnam(sys_get_temp_dir(), $prefix));
    }

    /**
     * Checks if a file exists.
     * @param string $path
     * @return bool
     */
    public static function exists(string $path): bool
    {
        $path = realpath(Directory::fixSlashes($path));
        return file_exists($path) && is_file($path);
    }

    /**
     * Deletes a file permanently, use with care!
     * @param string $path
     * @throws FileDoesNotExistException
     */
    public static function delete(string $path): void
    {
        $path = realpath(Directory::fixSlashes($path));

        if (!is_string($path) || !static::exists($path)) {
            throw new FileDoesNotExistException;
        }

        unlink($path);
    }
}
