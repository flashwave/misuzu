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
    private $path;

    /**
     * Directory separator used on this system, usually either \ for Windows or / for basically everything else.
     */
    public const SEPARATOR = DIRECTORY_SEPARATOR;

    /**
     * Get the path of this directory.
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return bool
     */
    public function isReadable(): bool
    {
        return is_readable($this->getPath());
    }

    /**
     * @return bool
     */
    public function isWritable(): bool
    {
        return is_writable($this->getPath());
    }

    /**
     * Fixes the path, sets proper slashes and checks if the directory exists.
     * @param string $path
     * @throws DirectoryDoesNotExistException
     */
    public function __construct(string $path)
    {
        $path = static::fixSlashes(rtrim($path, '/\\'));

        if (!static::exists($path)) {
            throw new DirectoryDoesNotExistException;
        }

        $this->path = realpath($path);
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
        }, glob($this->path . self::SEPARATOR . $pattern));
    }

    public function filename(string $filename): string
    {
        return $this->getPath() . self::SEPARATOR . $filename;
    }

    /**
     * Creates a directory if it doesn't already exist.
     * @param string $path
     * @return Directory
     * @throws DirectoryDoesNotExistException
     * @throws DirectoryExistsException
     */
    public static function create(string $path): Directory
    {
        if (static::exists($path)) {
            throw new DirectoryExistsException;
        }

        $on_windows = running_on_windows();
        $path = Directory::fixSlashes($path);
        $split_path = explode(self::SEPARATOR, $path);
        $existing_path = $on_windows ? '' : self::SEPARATOR;

        foreach ($split_path as $path_part) {
            $existing_path .= $path_part . self::SEPARATOR;

            if ($on_windows && substr($path_part, 1, 2) === ':\\') {
                continue;
            }

            if (!Directory::exists($existing_path)) {
                mkdir($existing_path);
            }
        }

        return new static($path);
    }

    /**
     * @param string $path
     * @return Directory
     * @throws DirectoryDoesNotExistException
     * @throws DirectoryExistsException
     */
    public static function createOrOpen(string $path): Directory
    {
        if (static::exists($path)) {
            return new Directory($path);
        } else {
            return Directory::create($path);
        }
    }

    /**
     * Deletes a directory, recursively if requested. Use $purge with care!
     * @param string $path
     * @param bool   $purge
     * @throws DirectoryDoesNotExistException
     * @throws FileDoesNotExistException
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
     * @param string $separator
     * @return string
     */
    public static function fixSlashes(string $path, string $separator = self::SEPARATOR): string
    {
        return str_replace(['/', '\\'], $separator, $path);
    }
}
