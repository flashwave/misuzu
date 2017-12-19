<?php
namespace Misuzu\IO;

use ErrorException;

/**
 * Wrapper for f* (file stream) functions and other cool stuff.
 * @package Misuzu\IO
 * @author Julian van de Groep <me@flash.moe>
 */
class File
{
    /**
     * Open + Read flag.
     */
    public const OPEN_READ = 'rb';

    /**
     * Open + Write flag.
     */
    public const OPEN_WRITE = 'xb';

    /**
     * Open + Read + Write flag.
     */
    public const OPEN_READ_WRITE = 'rb+';

    /**
     * Create (truncates!) + Write flag.
     */
    public const CREATE_WRITE = 'wb';

    /**
     * Create (truncates!) + Read + Write flag.
     */
    public const CREATE_READ_WRITE = 'wb+';

    /**
     * Open + Write flag.
     */
    public const OPEN_CREATE_WRITE = 'cb';

    /**
     * Open or Create + Read + Write flag.
     */
    public const OPEN_CREATE_READ_WRITE = 'cb+';

    /**
     * Resource/stream container.
     * @var resource
     */
    private $resource;

    /**
     * Filename.
     * @var string
     */
    public $name = '';

    /**
     * Real, fixed path.
     * @var string
     */
    public $path = '';

    /**
     * Filesize in bytes.
     * @var int
     */
    public $size = 0;

    /**
     * ID of file owner.
     * @var int
     */
    public $owner = 0;

    /**
     * ID of file's owning group.
     * @var int
     */
    public $group = 0;

    /**
     * Last time this file has been accessed.
     * @var int
     */
    public $accessTime = 0;

    /**
     * Last time this file has been modified.
     * @var int
     */
    public $modifiedTime = 0;

    /**
     * Current stream position.
     * @var int
     */
    public $position = 0;

    /**
     * Fixes path and opens resource.
     * @param string $path
     * @param string $mode
     * @throws FileDoesNotExistException
     * @throws IOException
     */
    public function __construct(string $path, string $mode)
    {
        $path = Directory::fixSlashes($path);
        $this->path = realpath($path);
        $this->name = basename($this->path);

        try {
            $this->resource = fopen($path, $mode, false);
        } catch (ErrorException $e) {
            throw new FileDoesNotExistException($e->getMessage());
        }

        if (!is_resource($this->resource)) {
            throw new IOException('Failed to create resource.');
        }

        $this->updateMetaData();
    }

    /**
     * Updates the meta data of the resource.
     */
    private function updateMetaData(): void
    {
        $meta = fstat($this->resource);
        $this->size = intval($meta['size']);
        $this->owner = intval($meta['uid']);
        $this->group = intval($meta['gid']);
        $this->accessTime = intval($meta['atime']);
        $this->modifiedTime = intval($meta['mtime']);
    }

    /**
     * Updates the position variable.
     */
    private function updatePosition(): void
    {
        $this->position = ftell($this->resource);
    }

    /**
     * Calls the close method.
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Creates an instance of a temporary file.
     * @param string $prefix
     * @return File
     */
    public static function temp(string $prefix = 'Misuzu'): File
    {
        return new static(tempnam(sys_get_temp_dir(), $prefix));
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

    /**
     * Closes the resource context.
     */
    public function close(): void
    {
        if (is_resource($this->resource)) {
            fclose($this->resource);
        }
    }

    /**
     * Moves the position to 0.
     */
    public function start(): void
    {
        rewind($this->resource);
        $this->updatePosition();
    }

    /**
     * Moves the position to the end of the file.
     */
    public function end(): void
    {
        fseek($this->resource, 0, SEEK_END);
        $this->updatePosition();
    }

    /**
     * Sets the position.
     * @param int $offset
     * @param bool $relative
     * @return int
     */
    public function position(int $offset, bool $relative = false): int
    {
        fseek($this->resource, $offset, $relative ? SEEK_CUR : SEEK_SET);
        $this->updatePosition();
        return $this->position;
    }

    /**
     * Checks if the current position is the end of the file.
     * @return bool
     */
    public function atEOF(): bool
    {
        return feof($this->resource);
    }

    /**
     * Tries to find the position of a string in the context.
     * @param string $string
     * @return int
     */
    public function find(string $string): int
    {
        while ($this->position < $this->size) {
            $find = strpos($this->read(8192), $string);

            if ($find !== false) {
                return $find + $this->position;
            }

            $this->position($this->position + 8192);
        }

        return -1;
    }

    /**
     * Locks the file and reads from it.
     * @param int $length
     * @throws IOException
     * @return string
     */
    public function read(int $length = null): string
    {
        if ($length === null) {
            $length = $this->size;
            $this->start();
        }

        flock($this->resource, LOCK_SH);
        $data = fread($this->resource, $length);
        flock($this->resource, LOCK_UN);

        if ($data === false) {
            throw new IOException('Read failed.');
        }

        $this->updateMetaData();

        return $data;
    }

    /**
     * Gets the character at the current position.
     * @return string
     */
    public function char(): string
    {
        return fgetc($this->resource);
    }

    /**
     * Locks the file, writes to the stream and flushes to file.
     * @param string $data
     * @param int $length
     * @throws IOException
     */
    public function write(string $data, int $length = 0): void
    {
        if ($length > 0) {
            $length = strlen($data);
        }

        flock($this->resource, LOCK_EX);
        $write = fwrite($this->resource, $data, $length);
        $flush = fflush($this->resource);
        flock($this->resource, LOCK_UN);

        if ($write === false || $flush === false) {
            throw new IOException('Write failed.');
        }

        $this->updateMetaData();
    }

    /**
     * The same as write except it moves to the end of the file first.
     * @param string $data
     * @param int $length
     */
    public function append(string $data, int $length = 0): void
    {
        $this->end();
        $this->write($data, $length);
    }
}
