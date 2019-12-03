<?php
namespace Misuzu\Imaging;

use InvalidArgumentException;
use UnexpectedValueException;

abstract class Image {
    public function __construct($pathOrWidth, int $height = -1) {
        if(!is_int($pathOrWidth) && !is_string($pathOrWidth))
            throw new InvalidArgumentException('The first argument must be or type string to open an image file, or int to set a width for a new image.');
    }

    public static function create($pathOrWidth, int $height = -1): Image {
        if(extension_loaded('imagick'))
            return new ImagickImage($pathOrWidth, $height);
        if(extension_loaded('gd'))
            return new GdImage($pathOrWidth, $height);
        throw new UnexpectedValueException('No image manipulation extensions are available.');
    }

    abstract public function getWidth(): int;
    abstract public function getHeight(): int;
    abstract public function hasFrames(): bool;
    abstract public function next(): bool;
    abstract public function resize(int $width, int $height): bool;
    abstract public function crop(int $width, int $height, int $x, int $y): bool;
    abstract public function setPage(int $width, int $height, int $x, int $y): bool;
    abstract public function save(string $path): bool;
    abstract public function destroy(): void;

    public function squareCrop(int $dimensions): void {
        $originalWidth = $this->getWidth();
        $originalHeight = $this->getHeight();

        if($originalWidth > $originalHeight) {
            $targetWidth = $originalWidth * $dimensions / $originalHeight;
            $targetHeight = $dimensions;
        } else {
            $targetWidth = $dimensions;
            $targetHeight = $originalHeight * $dimensions / $originalWidth;
        }

        do {
            $this->resize($targetWidth, $targetHeight);
            $this->crop(
                $dimensions, $dimensions,
                ($targetWidth - $dimensions) / 2,
                ($targetHeight - $dimensions) / 2
            );
            $this->setPage($dimensions, $dimensions, 0, 0);
        } while($this->next());
    }
}
