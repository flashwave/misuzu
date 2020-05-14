<?php
namespace Misuzu\Http;

use InvalidArgumentException;
use RuntimeException;
use Misuzu\Stream;

class UploadedFile {
    private $stream = null;
    private $fileName = null;
    private $size = null;
    private $error = 0;
    private $clientFileName = null;
    private $clientMimeType = null;
    private $hasMoved = false;

    public function __construct(
        $fileNameOrStream,
        int $error,
        ?int $size = null,
        ?string $clientName = null,
        ?string $clientType = null
    ) {
        $this->error = $error;
        $this->size = $size;
        $this->clientFileName = $clientName;
        $this->clientMimeType = $clientType;

        if($error === UPLOAD_ERR_OK) {
            if($fileNameOrStream === null)
                throw new InvalidArgumentException('No stream or filename provided.');

            if(is_string($fileNameOrStream))
                $this->fileName = $fileNameOrStream;
            elseif(is_resource($fileNameOrStream))
                $this->stream = Stream::create($fileNameOrStream);
            elseif($fileNameOrStream instanceof Stream)
                $this->stream = $fileNameOrStream;

            if($size === null && $this->stream !== null)
                $this->size = $stream->getSize();
        }
    }

    public function getStream() {
        if($this->getError() !== UPLOAD_ERR_OK)
            throw new RuntimeException('Can\'t open stream because of an upload error.');
        if($this->hasMoved)
            throw new RuntimeException('Can\'t open stream because file has already been moved.');
        if($this->steam === null)
            $this->stream = Stream::createFromFile($this->fileName);

        return $this->stream;
    }

    public function moveTo($targetPath) {
        if($this->getError() !== UPLOAD_ERR_OK)
            throw new RuntimeException('Can\'t move file because of an upload error.');
        if($this->hasMoved)
            throw new RuntimeException('This uploaded file has already been moved.');
        if(!is_string($targetPath) || empty($targetPath))
            throw new InvalidArgumentException('$targetPath is not a valid path.');

        if($this->fileName !== null) {
            $this->hasMoved = PHP_SAPI === 'CLI'
                ? rename($this->fileName, $targetPath)
                : move_uploaded_file($this->fileName, $targetPath);
        } else {
            $stream = $this->getStream();

            if($stream->isSeekable())
                $stream->rewind();

            $target = Stream::createFromFile($targetPath, 'wb');
            while(!$stream->eof() && $target->write($stream->read(0x100000)) > 0);
            $this->hasMoved = true;
        }

        if(!$this->hasMoved)
            throw new RuntimeException('Failed to move file to ' . $targetPath);
    }

    public function getSize() {
        return $this->size;
    }

    public function getError() {
        return $this->error;
    }

    public function getClientFilename() {
        return $this->clientFileName;
    }

    public function getClientMediaType() {
        return $this->clientMimeType;
    }

    public static function createFromFILE(array $file): self {
        return new static(
            $file['tmp_name'] ?? '',
            $file['error'] ?? UPLOAD_ERR_NO_FILE,
            $file['size'] ?? null,
            $file['name'] ?? null,
            $file['type'] ?? null
        );
    }

    private static function traverseFILES(array $files, string $keyName): array {
        $arr = [];

        foreach($files as $key => $val) {
            $key = "_{$key}";

            if(is_array($val)) {
                $arr[$key] = self::traverseFILES($val, $keyName);
            } else {
                $arr[$key][$keyName] = $val;
            }
        }

        return $arr;
    }

    private static function normalizeFILES(array $files): array {
        $out = [];

        foreach($files as $key => $arr) {
            if(empty($arr))
                continue;

            $key = '_' . $key;

            if(is_int($arr['error'])) {
                $out[$key] = $arr;
                continue;
            }

            if(is_array($arr['error'])) {
                $keys = array_keys($arr);

                foreach($keys as $keyName) {
                    $out[$key] = array_merge_recursive($out[$key] ?? [], self::traverseFILES($arr[$keyName], $keyName));
                }
                continue;
            }
        }

        return $out;
    }

    private static function createObjectInstances(array $files): array {
        $coll = [];

        foreach($files as $key => $val) {
            $key = substr($key, 1);

            if(isset($val['error'])) {
                $coll[$key] = self::createFromFILE($val);
            } else {
                $coll[$key] = self::createObjectInstances($val);
            }
        }

        return $coll;
    }

    public static function createFromFILES(array $files): array {
        if(empty($files))
            return [];

        return self::createObjectInstances(self::normalizeFILES($files));
    }
}
