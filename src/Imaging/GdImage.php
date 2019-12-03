<?php
namespace Misuzu\Imaging;

use InvalidArgumentException;

final class GdImage extends Image {
    private $gd;
    private int $type;

    private const CONSTRUCTORS = [
        IMAGETYPE_GIF => 'imagecreatefromgif',
        IMAGETYPE_JPEG => 'imagecreatefromjpeg',
        IMAGETYPE_PNG => 'imagecreatefrompng',
        IMAGETYPE_BMP => 'imagecreatefrombmp',
        IMAGETYPE_WBMP => 'imagecreatefromwbmp',
        IMAGETYPE_WEBP => 'imagecreatefromwebp',
    ];

    private const SAVERS = [
        IMAGETYPE_GIF => 'imagegif',
        IMAGETYPE_JPEG => 'imagejpeg',
        IMAGETYPE_PNG => 'imagepng',
        IMAGETYPE_BMP => 'imagebmp',
        IMAGETYPE_WBMP => 'imagewbmp',
        IMAGETYPE_WEBP => 'imagewebp',
    ];

    public function __construct($pathOrWidth, int $height = -1) {
        parent::__construct($pathOrWidth, $height);

        if(is_int($pathOrWidth)) {
            $this->gd = imagecreatetruecolor($pathOrWidth, $height < 1 ? $pathOrWidth : $height);
            $this->type = IMAGETYPE_PNG;
        } elseif(is_string($pathOrWidth)) {
            $imageInfo = getimagesize($pathOrWidth);

            if($imageInfo !== false) {
                $this->type = $imageInfo[2];

                if(isset(self::CONSTRUCTORS[$this->type]))
                    $this->gd = self::CONSTRUCTORS[$this->type]($pathOrWidth);
            }
        }

        if(!isset($this->gd)) {
            throw new InvalidArgumentException('Unsupported image format.');
        }
    }

    public function __destruct() {
        if(isset($this->gd))
            $this->destroy();
    }

    public function getWidth(): int {
        return imagesx($this->gd);
    }

    public function getHeight(): int {
        return imagesy($this->gd);
    }

    public function hasFrames(): bool {
        return false;
    }

    public function next(): bool {
        return false;
    }

    public function resize(int $width, int $height): bool {
        $resized = imagescale($this->gd, $width, $height, IMG_BICUBIC_FIXED);

        if($resized === false)
            return false;

        imagedestroy($this->gd);
        $this->gd = $resized;

        return true;
    }

    public function crop(int $width, int $height, int $x, int $y): bool {
        $cropped = imagecrop($this->gd, compact('width', 'height', 'x', 'y'));

        if($cropped === false)
            return false;

        imagedestroy($this->gd);
        $this->gd = $cropped;

        return true;
    }

    public function setPage(int $width, int $height, int $x, int $y): bool {
        return false;
    }

    public function save(string $path): bool {
        if(isset(self::SAVERS[$this->type]))
            return self::SAVERS[$this->type]($this->gd, $path);

        return false;
    }

    public function destroy(): void {
        if(imagedestroy($this->gd)) {
            $this->gd = null;
            $this->type = 0;
        }
    }
}
