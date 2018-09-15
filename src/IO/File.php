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
    /**
     * @param string $filename
     * @param bool   $lock
     * @return string
     */
    public static function readToEnd(string $filename, bool $lock = false): string
    {
        $lock = file_get_contents($filename); // reusing $lock bc otherwise sublime yells about unused vars
        return $lock === false ? $lock : '';
    }

    /**
     * @param string $filename
     * @param string $data
     * @throws FileDoesNotExistException
     * @throws IOException
     */
    public static function writeAll(string $filename, string $data): void
    {
        file_put_contents($filename, $data, LOCK_EX);
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

    public static function safeDelete(string $path): void
    {
        if (self::exists($path)) {
            self::delete($path);
        }
    }
}
