<?php
namespace Misuzu\IO;

use ErrorException;

/**
 * Class FileStream
 * @package Misuzu\IO
 */
class FileStream extends Stream
{
    /**
     * Open a file for reading only.
     */
    public const MODE_READ = 0x1;

    /**
     * Open a file for writing only.
     */
    public const MODE_WRITE = 0x2;

    /**
     * Truncate a file.
     */
    private const MODE_TRUNCATE_RAW = 0x4;

    /**
     * Append to a file.
     */
    private const MODE_APPEND_RAW = 0x8;

    /**
     * Open a file for reading and writing.
     */
    public const MODE_READ_WRITE = self::MODE_READ | self::MODE_WRITE;

    /**
     * Truncate and open a file for writing.
     */
    public const MODE_TRUNCATE = self::MODE_TRUNCATE_RAW | self::MODE_WRITE;

    /**
     * Open a file for writing and append to the end.
     */
    public const MODE_APPEND = self::MODE_APPEND_RAW | self::MODE_WRITE;

    protected $fileHandle;
    protected $filePath;
    protected $fileMode;
    protected $isLocked;

    /**
     * FileStream constructor.
     * @param string $path
     * @param int    $mode
     * @param bool   $lock
     * @throws FileDoesNotExistException
     * @throws IOException
     */
    public function __construct(string $path, int $mode, bool $lock = true)
    {
        $this->isLocked = $lock;
        $this->filePath = $path;
        $this->fileMode = $mode;

        try {
            $this->fileHandle = fopen($this->filePath, static::constructFileMode($this->fileMode));
        } catch (ErrorException $ex) {
            throw new FileDoesNotExistException($ex->getMessage());
        }

        $this->ensureHandleActive();

        if ($this->isLocked) {
            flock($this->fileHandle, LOCK_EX | LOCK_NB);
        }
    }

    /**
     * Clears up the resources used by this stream.
     */
    public function __destruct()
    {
        if (!is_resource($this->fileHandle)) {
            return;
        }

        $this->close();
    }

    /**
     * Creates a file mode string from our own flags.
     * @param int $mode
     * @return string
     * @throws IOException
     */
    protected static function constructFileMode(int $mode): string
    {
        $mode_read = ($mode & static::MODE_READ) > 0;
        $mode_write = ($mode & static::MODE_WRITE) > 0;
        $mode_truncate = ($mode & static::MODE_TRUNCATE_RAW) > 0;
        $mode_append = ($mode & static::MODE_APPEND_RAW) > 0;

        // why would you ever
        if ($mode_append && $mode_truncate) {
            throw new IOException("Can't append and truncate at the same time.");
        }

        if (($mode_append || $mode_truncate) && !$mode_write) {
            throw new IOException("Can't append or truncate without write privileges.");
        }

        $mode_string = '';

        if ($mode_append) {
            $mode_string = 'a';
        } elseif ($mode_truncate) {
            $mode_string = 'w';
        } elseif ($mode_write) {
            $mode_string = 'c';
        }

        $mode_string .= 'b';

        if ($mode_read) {
            if (strlen($mode_string) < 2) {
                $mode_string = 'r' . $mode_string;
            } else {
                $mode_string .= '+';
            }
        }

        // should be at least two characters because of the b flag
        if (strlen($mode_string) < 2) {
            throw new IOException('Failed to construct mode???');
        }

        return $mode_string;
    }

    /**
     * @return int
     * @throws IOException
     */
    public function getResource(): int
    {
        $this->ensureHandleActive();
        return $this->fileHandle;
    }

    /**
     * @throws IOException
     */
    protected function ensureHandleActive(): void
    {
        if (!is_resource($this->fileHandle)) {
            throw new IOException("No active file handle.");
        }
    }

    /**
     * @throws IOException
     */
    protected function ensureCanRead(): void
    {
        if (!$this->getCanRead()) {
            throw new IOException('This stream cannot perform read operations.');
        }
    }

    /**
     * @throws IOException
     */
    protected function ensureCanWrite(): void
    {
        if (!$this->getCanWrite()) {
            throw new IOException('This stream cannot perform write operations.');
        }
    }

    /**
     * @throws IOException
     */
    protected function ensureCanSeek(): void
    {
        if (!$this->getCanSeek()) {
            throw new IOException('This stream cannot perform seek operations.');
        }
    }

    /**
     * @return bool
     */
    public function getCanRead(): bool
    {
        return ($this->fileMode & static::MODE_READ) > 0 && is_readable($this->filePath);
    }

    /**
     * @return bool
     */
    public function getCanSeek(): bool
    {
        return ($this->fileMode & static::MODE_APPEND_RAW) == 0 && $this->getCanRead();
    }

    /**
     * @return bool
     */
    public function getCanTimeout(): bool
    {
        return false;
    }

    /**
     * @return bool
     */
    public function getCanWrite(): bool
    {
        return ($this->fileMode & static::MODE_WRITE) > 0 && is_writable($this->filePath);
    }

    /**
     * @return int
     * @throws IOException
     */
    public function getLength(): int
    {
        $this->ensureHandleActive();
        return fstat($this->fileHandle)['size'];
    }

    /**
     * @return int
     * @throws IOException
     */
    public function getPosition(): int
    {
        $this->ensureHandleActive();
        return ftell($this->fileHandle);
    }

    /**
     * @return int
     */
    public function getReadTimeout(): int
    {
        return -1;
    }

    /**
     * @return int
     */
    public function getWriteTimeout(): int
    {
        return -1;
    }

    /**
     * @throws IOException
     */
    public function flush(): void
    {
        $this->ensureHandleActive();
        fflush($this->fileHandle);
    }

    /**
     * @throws IOException
     */
    public function close(): void
    {
        $this->ensureHandleActive();

        if ($this->isLocked) {
            flock($this->fileHandle, LOCK_UN | LOCK_NB);
        }

        fclose($this->fileHandle);
    }

    /**
     * @param int $length
     * @return string
     * @throws IOException
     */
    public function read(int $length): string
    {
        $this->ensureHandleActive();
        $this->ensureCanRead();

        $read = fread($this->fileHandle, $length);

        if ($read === false) {
            throw new IOException('Read failed.');
        }

        return $read;
    }

    /**
     * @return int
     * @throws IOException
     */
    public function readChar(): int
    {
        $this->ensureHandleActive();
        $this->ensureCanRead();

        return ord(fgetc($this->fileHandle));
    }

    /**
     * @param string $data
     * @return int
     * @throws IOException
     */
    public function write(string $data): int
    {
        $this->ensureHandleActive();
        $this->ensureCanWrite();

        $write = fwrite($this->fileHandle, $data);

        if ($write === false) {
            throw new IOException('Write failed.');
        }

        return $write;
    }

    /**
     * @param int $char
     * @throws IOException
     */
    public function writeChar(int $char): void
    {
        $this->write(chr($char));
    }

    /**
     * @param int $offset
     * @param int $origin
     * @throws IOException
     */
    public function seek(int $offset, int $origin): void
    {
        $this->ensureHandleActive();
        $this->ensureCanSeek();

        switch ($origin) {
            case Stream::ORIGIN_BEGIN:
                $origin = SEEK_SET;
                break;

            case Stream::ORIGIN_END:
                $origin = SEEK_END;
                break;

            case Stream::ORIGIN_CURRENT:
                $origin = SEEK_CUR;
                break;

            default:
                throw new IOException('Invalid seek origin.');
        }

        if (fseek($this->fileHandle, $offset, $origin) !== 0) {
            throw new IOException('Seek operation failed.');
        }
    }
}
