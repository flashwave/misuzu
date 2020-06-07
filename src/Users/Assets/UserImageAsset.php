<?php
namespace Misuzu\Users\Assets;

use JsonSerializable;
use Misuzu\Config;
use Misuzu\Users\User;

class UserImageAssetFileCreationFailedException extends UserAssetException {}
class UserImageAssetFileNotFoundException extends UserAssetException {}
class UserImageAssetInvalidImageException extends UserAssetException {}
class UserImageAssetInvalidTypeException extends UserAssetException {}
class UserImageAssetInvalidDimensionsException extends UserAssetException {}
class UserImageAssetFileTooLargeException extends UserAssetException {}
class UserImageAssetMoveFailedException extends UserAssetException {}

abstract class UserImageAsset implements JsonSerializable, UserImageAssetInterface {
    public const PUBLIC_STORAGE = '/msz-storage';

    public const TYPE_PNG = IMAGETYPE_PNG;
    public const TYPE_JPG = IMAGETYPE_JPEG;
    public const TYPE_GIF = IMAGETYPE_GIF;

    public const TYPES_EXT = [
        self::TYPE_PNG => 'png',
        self::TYPE_JPG => 'jpg',
        self::TYPE_GIF => 'gif',
    ];

    private $user;

    public function __construct(User $user) {
        $this->user = $user;
    }

    public function getUser(): User {
        return $this->user;
    }

    public abstract function getMaxWidth(): int;
    public abstract function getMaxHeight(): int;
    public abstract function getMaxBytes(): int;

    public function getAllowedTypes(): array {
        return [self::TYPE_PNG, self::TYPE_JPG, self::TYPE_GIF];
    }
    public function isAllowedType(int $type): bool {
        return in_array($type, $this->getAllowedTypes());
    }

    private function getImageSize(): array {
        return $this->isPresent() && ($imageSize = getimagesize($this->getPath())) ? $imageSize : [];
    }
    public function getWidth(): int {
        return $this->getImageSize()[0] ?? -1;
    }
    public function getHeight(): int {
        return $this->getImageSize()[1] ?? -1;
    }
    public function getIntType(): int {
        return $this->getImageSize()[2] ?? -1;
    }
    public function getMimeType(): string {
        return mime_content_type($this->getPath());
    }
    public function getFileExtension(): string {
        return self::TYPES_EXT[$this->getIntType()] ?? 'img';
    }

    public abstract function getFileName(): string;

    public abstract function getRelativePath(): string;
    public function isPresent(): bool {
        return is_file($this->getPath());
    }

    public function getPublicPath(): string {
        return self::PUBLIC_STORAGE . '/' . $this->getRelativePath();
    }

    public function delete(): void {
        if($this->isPresent())
            unlink($this->getPath());
    }

    public function getStoragePath(): string {
        return Config::get('storage.path', Config::TYPE_STR, MSZ_ROOT . DIRECTORY_SEPARATOR . 'store');
    }

    public function getPath(): string {
        return $this->getStoragePath() . DIRECTORY_SEPARATOR . $this->getRelativePath();
    }

    public function setFromPath(string $path): void {
        if(!is_file($path))
            throw new UserImageAssetFileNotFoundException;

        $imageInfo = getimagesize($path);
        if($imageInfo === false || count($imageInfo) < 3 || $imageInfo[0] < 1 || $imageInfo[1] < 1)
            throw new UserImageAssetInvalidImageException;

        if(!self::isAllowedType($imageInfo[2]))
            throw new UserImageAssetInvalidTypeException;

        if($imageInfo[0] > $this->getMaxWidth() || $imageInfo[1] > $this->getMaxHeight())
            throw new UserImageAssetInvalidDimensionsException;

        if(filesize($path) > $this->getMaxBytes())
            throw new UserImageAssetFileTooLargeException;

        $this->delete();

        $targetPath = $this->getPath();
        $targetDir = dirname($targetPath);
        if(!is_dir($targetDir))
            mkdir($targetDir, 0775, true);

        if(is_uploaded_file($path) ? !move_uploaded_file($path, $targetPath) : !copy($path, $targetPath))
            throw new UserImageAssetMoveFailedException;
    }

    public function setFromData(string $data): void {
        $file = tempnam(sys_get_temp_dir(), 'msz');
        if($file === null || !is_file($file))
            throw new UserImageAssetFileCreationFailedException;
        chmod($file, 0664);
        file_put_contents($file, $data);
        self::setFromPath($file);
        unlink($file);
    }

    public function jsonSerialize() {
        return [
            'is_present' => $this->isPresent(),
            'width' => $this->getWidth(),
            'height' => $this->getHeight(),
            'mime' => $this->getMimeType(),
        ];
    }
}
