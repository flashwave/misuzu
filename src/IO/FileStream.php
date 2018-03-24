<?php
namespace Misuzu\IO;

use ErrorException;

class FileStream extends Stream
{
    public const MODE_READ = 0x1;
    public const MODE_WRITE = 0x2;
    private const MODE_TRUNCATE_RAW = 0x4;
    private const MODE_APPEND_RAW = 0x8;
    public const MODE_READ_WRITE = self::MODE_READ | self::MODE_WRITE;
    public const MODE_TRUNCATE = self::MODE_TRUNCATE_RAW | self::MODE_WRITE;
    public const MODE_APPEND = self::MODE_APPEND_RAW | self::MODE_WRITE;

    protected $fileHandle;
    protected $filePath;
    protected $fileMode;
    protected $isLocked;

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

    public function __destruct()
    {
        if (!is_resource($this->fileHandle)) {
            return;
        }

        $this->close();
    }

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
            throw IOException('Failed to construct mode???');
        }

        return $mode_string;
    }

    public function getResource(): resource
    {
        $this->ensureHandleActive();
        return $this->fileHandle;
    }

    protected function ensureHandleActive(): void
    {
        if (!is_resource($this->fileHandle)) {
            throw new IOException("No active file handle.");
        }
    }

    protected function ensureCanRead(): void
    {
        if (!$this->getCanRead()) {
            throw new IOException('This stream cannot perform read operations.');
        }
    }

    protected function ensureCanWrite(): void
    {
        if (!$this->getCanWrite()) {
            throw new IOException('This stream cannot perform write operations.');
        }
    }

    protected function ensureCanSeek(): void
    {
        if (!$this->getCanSeek()) {
            throw new IOException('This stream cannot perform seek operations.');
        }
    }

    public function getCanRead(): bool
    {
        return ($this->fileMode & static::MODE_READ) > 0 && is_readable($this->filePath);
    }

    public function getCanSeek(): bool
    {
        return ($this->fileMode & static::MODE_APPEND_RAW) == 0 && $this->getCanRead();
    }

    public function getCanTimeout(): bool
    {
        return false;
    }

    public function getCanWrite(): bool
    {
        return ($this->fileMode & static::MODE_WRITE) > 0 && is_writable($this->filePath);
    }

    public function getLength(): int
    {
        $this->ensureHandleActive();
        return fstat($this->fileHandle)['size'];
    }

    public function getPosition(): int
    {
        $this->ensureHandleActive();
        return ftell($this->fileHandle);
    }

    public function getReadTimeout(): int
    {
        return -1;
    }

    public function getWriteTimeout(): int
    {
        return -1;
    }

    public function flush(): void
    {
        $this->ensureHandleActive();
        fflush($this->fileHandle);
    }

    public function close(): void
    {
        $this->ensureHandleActive();

        if ($this->isLocked) {
            flock($this->fileHandle, LOCK_UN | LOCK_NB);
        }

        fclose($this->fileHandle);
    }

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

    public function readChar(): int
    {
        $this->ensureHandleActive();
        $this->ensureCanRead();

        return ord(fgetc($this->fileHandle));
    }

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

    public function writeChar(int $char): void
    {
        $this->write(chr($char), 0, 1);
    }

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
