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

    abstract public function getCanRead(): bool;
    abstract public function getCanSeek(): bool;
    abstract public function getCanTimeout(): bool;
    abstract public function getCanWrite(): bool;
    abstract public function getLength(): int;
    abstract public function getPosition(): int;
    abstract public function getReadTimeout(): int;
    abstract public function getWriteTimeout(): int;

    abstract public function flush(): void;
    abstract public function close(): void;
    abstract public function seek(int $offset, int $origin): void;

    abstract public function read(int $length): string;
    abstract public function readChar(): int;

    abstract public function write(string $data): int;
    abstract public function writeChar(int $char): void;
}
