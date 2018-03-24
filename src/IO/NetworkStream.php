<?php
namespace Misuzu\IO;

class NetworkStream extends Stream
{
    protected $resourceHandle;
    protected $host;
    protected $port;
    protected $timeout;

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

    public function __destruct()
    {
        if (!is_resource($this->resourceHandle)) {
            return;
        }

        $this->close();
    }

    public function getResource(): resource
    {
        $this->ensureHandleActive();
        return $this->resourceHandle;
    }

    protected function ensureHandleActive(): void
    {
        if (!is_resource($this->resourceHandle)) {
            throw new IOException("No active file handle.");
        }
    }

    public function getCanRead(): bool
    {
        return true;
    }

    public function getCanSeek(): bool
    {
        return false;
    }

    public function getCanTimeout(): bool
    {
        return true;
    }

    public function getCanWrite(): bool
    {
        return true;
    }

    public function getLength(): int
    {
        return -1;
    }

    public function getPosition(): int
    {
        return -1;
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
        fflush($this->resourceHandle);
    }

    public function close(): void
    {
        $this->ensureHandleActive();
        fclose($this->resourceHandle);
    }

    public function read(int $length): string
    {
        $this->ensureHandleActive();

        $read = fread($this->resourceHandle, $length);

        if ($read === false) {
            throw new IOException('Read failed.');
        }

        return $read;
    }

    public function readChar(): int
    {
        $this->ensureHandleActive();

        return ord(fgetc($this->resourceHandle));
    }

    public function write(string $data): int
    {
        $this->ensureHandleActive();

        $write = fwrite($this->resourceHandle, $data);

        if ($write === false) {
            throw new IOException('Write failed.');
        }

        return $write;
    }

    public function writeChar(int $char): void
    {
        $this->write(chr($char), 0, 1);
    }

    /**
     * @SuppressWarnings("unused")
     */
    public function seek(int $offset, int $origin): void
    {
        throw new IOException('This stream cannot perform seek operations.');
    }
}
