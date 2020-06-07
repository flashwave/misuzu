<?php
namespace Misuzu\Users\Assets;

class StaticUserImageAsset implements UserImageAssetInterface {
    private $path = '';
    private $filename = '';
    private $relativePath = '';

    public function __construct(string $path, string $absolutePart = '') {
        $this->path = $path;
        $this->filename = basename($path);
        $this->relativePath = substr($path, strlen($absolutePart));
    }

    public function isPresent(): bool {
        return is_file($this->path);
    }

    public function getMimeType(): string {
        return mime_content_type($this->path);
    }

    public function getPublicPath(): string {
        return $this->relativePath;
    }

    public function getFileName(): string {
        return $this->filename;
    }
}
