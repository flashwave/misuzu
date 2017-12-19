<?php
namespace Misuzu\IO;

/**
 * Directory container.
 * @package Misuzu\IO
 * @author Julian van de Groep <me@flash.moe>
 */
class Directory
{
    /**
     * Path to this directory.
     * @var string
     */
    public $path;

    /**
     * Fixes the path, sets proper slashes and checks if the directory exists.
     * @param string $path
     * @throws DirectoryDoesNotExistException
     */
    public function __construct(string $path)
    {
        $this->path = static::fixSlashes(rtrim($path, '/\\'));

        if (!static::exists($this->path)) {
            throw new DirectoryDoesNotExistException;
        }
    }

    /**
     * Gets contents of this directory, subdirs get their own instance.
     * @param string $pattern
     * @return array
     */
    public function files(string $pattern = '*'): array
    {
        return array_map(function ($path) {
            if (static::exists($path)) {
                return new static($path);
            }

            return realpath($path);
        }, glob($this->path . '/' . $pattern));
    }

    /**
     * Creates a directory if it doesn't already exist.
     * @param string $path
     * @throws DirectoryExistsException
     * @return Directory
     */
    public static function create(string $path): Directory
    {
        $path = static::fixSlashes($path);

        if (static::exists($path)) {
            throw new DirectoryExistsException;
        }

        mkdir($path);

        return new static($path);
    }

    /**
     * Deletes a directory, recursively if requested. Use $purge with care!
     * @param string $path
     * @param bool $purge
     * @throws DirectoryDoesNotExistException
     */
    public static function delete(string $path, bool $purge = false): void
    {
        $path = static::fixSlashes($path);

        if (!static::exists($path)) {
            throw new DirectoryDoesNotExistException;
        }

        if ($purge) {
            $dir = new static($path);

            foreach ($dir->files() as $file) {
                if ($file instanceof self) {
                    static::delete($file->path, true);
                } else {
                    File::delete($file);
                }
            }
        }

        rmdir($path);
    }

    /**
     * Checks if a directory exists.
     * @param string $path
     * @return bool
     */
    public static function exists(string $path): bool
    {
        $path = static::fixSlashes($path);
        return file_exists($path) && is_dir($path);
    }

    /**
     * Fixes operating system specific slashing.
     * @param string $path
     * @return string
     */
    public static function fixSlashes(string $path): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }
}
