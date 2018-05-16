<?php
namespace Misuzu\IO;

/**
 * Provides a wrapper for fsockopen.
 * Class NetworkStream
 * @package Misuzu\IO
 */
class NetworkStream extends Stream
{
    protected $resourceHandle;
    protected $host;
    protected $port;
    protected $timeout;

    /**
     * NetworkStream constructor.
     * @param string   $host
     * @param int      $port
     * @param int|null $timeout
     * @throws IOException
     */
    public function __construct(string $host, int $port = -1, ?int $timeout = null)
    {
        $this->host = $host;
        $this->port = $port;
        $this->timeout = $timeout ?? (int)ini_get('default_socket_timeout');

        try {
            $this->resourceHandle = fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);
        } catch (ErrorException $ex) {
            throw new IOException($ex->getMessage());
        }

        if ($this->resourceHandle === false) {
            throw new IOException("[{$errno}] {$errstr}");
        }

        $this->ensureHandleActive();
    }

    /**
     * Cleans the resources used by this object up.
     */
    public function __destruct()
    {
        if (!is_resource($this->resourceHandle)) {
            return;
        }

        $this->close();
    }

    /**
     * @return resource
     * @throws IOException
     */
    public function getResource(): resource
    {
        $this->ensureHandleActive();
        return $this->resourceHandle;
    }

    /**
     * @throws IOException
     */
    protected function ensureHandleActive(): void
    {
        if (!is_resource($this->resourceHandle)) {
            throw new IOException("No active file handle.");
        }
    }

    /**
     * @return bool
     */
    public function getCanRead(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function getCanSeek(): bool
    {
        return false;
    }

    /**
     * @return bool
     */
    public function getCanTimeout(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function getCanWrite(): bool
    {
        return true;
    }

    /**
     * @return int
     */
    public function getLength(): int
    {
        return -1;
    }

    /**
     * @return int
     */
    public function getPosition(): int
    {
        return -1;
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
        fflush($this->resourceHandle);
    }

    /**
     * @throws IOException
     */
    public function close(): void
    {
        $this->ensureHandleActive();
        fclose($this->resourceHandle);
    }

    /**
     * @param int $length
     * @return string
     * @throws IOException
     */
    public function read(int $length): string
    {
        $this->ensureHandleActive();

        $read = fread($this->resourceHandle, $length);

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
        return ord(fgetc($this->resourceHandle));
    }

    /**
     * @param string $data
     * @return int
     * @throws IOException
     */
    public function write(string $data): int
    {
        $this->ensureHandleActive();

        $write = fwrite($this->resourceHandle, $data);

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
        $this->write(chr($char), 0, 1);
    }

    /**
     * @param int $offset
     * @param int $origin
     * @throws IOException
     * @SuppressWarnings("unused")
     */
    public function seek(int $offset, int $origin): void
    {
        throw new IOException('This stream cannot perform seek operations.');
    }
}
