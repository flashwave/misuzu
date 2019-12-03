<?php
namespace Misuzu\Imaging;

use Imagick;
use InvalidArgumentException;

final class ImagickImage extends Image {
    private ?Imagick $imagick = null;

    public function __construct($pathOrWidth, int $height = -1) {
        parent::__construct($pathOrWidth, $height);

        if(is_int($pathOrWidth)) {
            $this->imagick = new Imagick();
            $this->newImage($pathOrWidth, $height < 1 ? $pathOrWidth : $height, 'none');
            $this->setImageFormat('png');
        } elseif(is_string($pathOrWidth)) {
            $imagick = new Imagick($pathOrWidth);
            $imagick->setImageFormat($imagick->getNumberImages() > 1 ? 'gif' : 'png');
            $this->imagick = $imagick->coalesceImages();
        }

        if(!isset($this->imagick))
            throw new InvalidArgumentException('Unsupported image format.');
    }

    public function __destruct() {
        if(isset($this->imagick))
            $this->destroy();
    }

    public function getImagick(): Imagick {
        return $this->imagick;
    }

    public function getWidth(): int {
        return $this->imagick->getImageWidth();
    }

    public function getHeight(): int {
        return $this->imagick->getImageHeight();
    }

    public function hasFrames(): bool {
        return $this->imagick->getNumberImages() > 1;
    }

    public function next(): bool {
        return $this->imagick->nextImage();
    }

    public function resize(int $width, int $height): bool {
        return $this->imagick->resizeImage(
            $width, $height, Imagick::FILTER_LANCZOS, 0.9
        );
    }

    public function crop(int $width, int $height, int $x, int $y): bool {
        return $this->imagick->cropImage($width, $height, $x, $y);
    }

    public function setPage(int $width, int $height, int $x, int $y): bool {
        return $this->imagick->setImagePage($width, $height, $x, $y);
    }

    public function save(string $path): bool {
        return $this->imagick
            ->deconstructImages()
            ->writeImages($path, true);
    }

    public function destroy(): void {
        if($this->imagick->destroy())
            $this->imagick = null;
    }
}
