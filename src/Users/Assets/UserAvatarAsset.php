<?php
namespace Misuzu\Users\Assets;

use Misuzu\Config;
use Misuzu\Imaging\Image;
use Misuzu\Users\User;

class UserAvatarAsset extends UserImageAsset implements UserAssetScalableInterface {
    private const FORMAT = 'avatars/%s/%d.msz';
    private const DIR_ORIG = 'original';
    private const DIR_SIZE = '%1$dx%1$d';

    public const DEFAULT_DIMENSION = 200;
    public const DIMENSIONS = [
        40, 60, 80, 100, 120, 200, 240,
    ];

    private const MAX_RES  = 2000;
    private const MAX_BYTES  = 1048576;

    public function getMaxWidth(): int {
        return Config::get('avatar.max_res', Config::TYPE_INT, self::MAX_RES);
    }
    public function getMaxHeight(): int {
        return $this->getMaxWidth();
    }
    public function getMaxBytes(): int {
        return Config::get('avatar.max_size', Config::TYPE_INT, self::MAX_BYTES);
    }

    public function getUrl(): string {
        return url('user-avatar', ['user' => $this->getUser()->getId()]);
    }
    public function getScaledUrl(int $dims): string {
        return url('user-avatar', ['user' => $this->getUser()->getId(), 'res' => $dims]);
    }

    public static function clampDimensions(int $dimensions): int {
        $closest = null;
        foreach(self::DIMENSIONS as $dims)
            if($closest === null || abs($dimensions - $closest) >= abs($dims - $dimensions))
                $closest = $dims;
        return $closest;
    }

    public function getFileName(): string {
        return sprintf('avatar-%1$d.%2$s', $this->getUser()->getId(), $this->getFileExtension());
    }
    public function getScaledFileName(int $dims): string {
        return sprintf('avatar-%1$d-%3$dx%3$d.%2$s', $this->getUser()->getId(), $this->getScaledFileExtension($dims), self::clampDimensions($dims));
    }

    public function getScaledMimeType(int $dims): string {
        if(!$this->isScaledPresent($dims))
            return '';
        return mime_content_type($this->getScaledPath($dims));
    }
    public function getScaledFileExtension(int $dims): string {
        $imageSize = getimagesize($this->getScaledPath($dims));
        if($imageSize === null)
            return 'img';
        return self::TYPES_EXT[$imageSize[2]] ?? 'img';
    }

    public function getRelativePath(): string {
        return sprintf(self::FORMAT, self::DIR_ORIG, $this->getUser()->getId());
    }
    public function getScaledRelativePath(int $dims): string {
        $dims = self::clampDimensions($dims);
        return sprintf(self::FORMAT, sprintf(self::DIR_SIZE, $dims), $this->getUser()->getId());
    }

    public function getScaledPath(int $dims): string {
        return $this->getStoragePath() . DIRECTORY_SEPARATOR . $this->getScaledRelativePath($dims);
    }
    public function isScaledPresent(int $dims): bool {
        return is_file($this->getScaledPath($dims));
    }
    public function deleteScaled(int $dims): void {
        if($this->isScaledPresent($dims))
            unlink($this->getScaledPath($dims));
    }
    public function ensureScaledExists(int $dims): void {
        if(!$this->isPresent())
            return;
        $dims = self::clampDimensions($dims);

        if($this->isScaledPresent($dims))
            return;

        $scaledPath = $this->getScaledPath($dims);
        $scaledDir = dirname($scaledPath);
        if(!is_dir($scaledDir))
            mkdir($scaledDir, 0775, true);

        $scale = Image::create($this->getPath());
        $scale->squareCrop($dims);
        $scale->save($scaledPath);
        $scale->destroy();
    }

    public function getPublicScaledPath(int $dims): string {
        return self::PUBLIC_STORAGE . '/' . $this->getScaledRelativePath($dims);
    }

    public function delete(): void {
        parent::delete();
        foreach(self::DIMENSIONS as $dims)
            $this->deleteScaled($dims);
    }
}
