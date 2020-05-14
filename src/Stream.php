<?php
namespace Misuzu;

use Exception;
use InvalidArgumentException;
use RuntimeException;

class Stream {
    private $stream = null;
    private $metaData = [];
    private $seekable = false;
    private $readable = false;
    private $writable = false;
    private $uri = null;
    private $size = null;

    private const READABLE = [
        'r',  'rb',  'r+', 'r+b', 'w+', 'w+b',
        'a+', 'a+b', 'x+', 'x+b', 'c+', 'c+b',
    ];
    private const WRITABLE = [
        'r+', 'r+b', 'w',  'wb',  'w+', 'w+b',
        'a',  'ab',  'a+', 'a+b', 'x',  'xb',
        'x+', 'x+b', 'c',  'cb',  'c+', 'c+b',
    ];

    public function __construct($resource) {
        if(is_string($resource)) {
            $mem = fopen('php://temp', 'rb+');
            fwrite($mem, $resource);
            $resource = $mem;
        }

        if(!is_resource($resource))
            throw new InvalidArgumentException('Provided argument is not valid.');

        $this->stream = $resource;
        $metaData = $this->getMetadata();

        $this->uri = $metaData['uri'] ?? null;
        $this->readable = in_array($metaData['mode'], self::READABLE);
        $this->writable = in_array($metaData['mode'], self::WRITABLE);
        $this->seekable = $metaData['seekable'] && fseek($this->stream, 0, SEEK_CUR) === 0;
    }

    public static function create($contents = ''): Stream {
        if($contents instanceof Stream)
            return $contents;

        return new static($contents);
    }
    public static function createFromFile(string $filename, string $mode = 'rb'): Stream {
        if(!in_array($mode[0], ['r', 'w', 'a', 'x', 'c']))
            throw new InvalidArgumentException("Provided mode ({$mode}) is invalid.");

        $file = @fopen($filename, $mode);

        if($file === false)
            throw new RuntimeException("Wasn't able to open '{$filename}'.");

        return self::create($file);
    }

    public function getMetadata($key = null) {
        $hasKey = $key !== null;

        if(!isset($this->stream))
            return $hasKey ? null : [];

        $metaData = stream_get_meta_data($this->stream);

        if(!$hasKey)
            return $metaData;

        return $metaData[$key] ?? null;
    }

    public function isReadable(): bool {
        return $this->readable;
    }

    public function read($length): int {
        if(!$this->isReadable())
            throw RuntimeException('Can\'t read from this stream.');

        return fread($this->stream, $length);
    }

    public function getContents(): string {
        if(!isset($this->stream))
            throw new RuntimeException('Can\'t read contents of stream.');

        if(($contents = stream_get_contents($this->stream)) === false)
            throw new RuntimeException('Failed to read contents of stream.');

        return $contents;
    }

    public function isWritable(): bool {
        return $this->writable;
    }

    public function write($string): int {
        if(!$this->isWritable())
            throw new RuntimeException('Can\'t write to this stream.');

        $this->size = null;

        if(($count = fwrite($this->stream, $string)) === false)
            throw new RuntimeException('Failed to write to this stream.');

        return $count;
    }

    public function isSeekable(): bool {
        return $this->seekable;
    }

    public function seek($offset, $whence = SEEK_SET): void {
        if(!$this->isSeekable())
            throw new RuntimeException('Can\'t seek in this stream.');

        if(fseek($this->stream, $offset, $whence) === -1)
            throw new RuntimeException("Failed to seek to position {$offset} ({$whence}).");
    }

    public function rewind(): void {
        $this->seek(0);
    }

    public function tell(): int {
        if(!isset($this->stream))
            throw new RuntimeException('Can\'t determine the position of a detached stream.');
        if(($pos = ftell($this->stream)) === false)
            throw new RuntimeException('Can\'t tell position in stream.');

        return $pos;
    }

    public function eof(): bool {
        return !isset($this->stream) || feof($this->stream);
    }

    public function getSize(): ?int {
        if($this->size !== null)
            return $this->size;

        if(!isset($this->stream))
            return null;

        if(!empty($this->uri))
            clearstatcache($this->uri);

        $stats = fstat($this->stream);
        if(isset($stats['size']))
            return $this->size = $stats['size'];

        return null;
    }

    public function detach() {
        if(!isset($this->stream))
            return null;

        $stream = $this->stream;
        $this->stream = $this->size = $this->uri = null;
        $this->readable = $this->writable = $this->seekable = false;

        return $stream;
    }

    public function close(): void {
        $stream = $this->detach();

        if(is_resource($stream))
            fclose($stream);
    }

    public function __toString() {
        try {
            if($this->isSeekable())
                $this->rewind();

            return $this->getContents();
        } catch(Exception $ex) {
            return '';
        }
    }

    public function __destruct() {
        $this->close();
    }
}
