<?php
namespace Misuzu\IO;

use InvalidArgumentException;

abstract class Stream
{
    public const ORIGIN_CURRENT = 0;
    public const ORIGIN_BEGIN = 1;
    public const ORIGIN_END = 2;

    public function __get(string $name)
    {
        $name = 'get' . ucfirst($name);

        if (method_exists(static::class, $name)) {
            return $this->{$name}();
        }

        throw new InvalidArgumentException;
    }

    abstract protected function getCanRead(): bool;
    abstract protected function getCanSeek(): bool;
    abstract protected function getCanTimeout(): bool;
    abstract protected function getCanWrite(): bool;
    abstract protected function getLength(): int;
    abstract protected function getPosition(): int;
    abstract protected function getReadTimeout(): int;
    abstract protected function getWriteTimeout(): int;

    abstract public function flush(): void;
    abstract public function close(): void;
    abstract public function seek(int $offset, int $origin): void;

    abstract public function read(int $length): string;
    abstract public function readChar(): int;

    abstract public function write(string $data): int;
    abstract public function writeChar(int $char): void;
}
