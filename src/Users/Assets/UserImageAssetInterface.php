<?php
namespace Misuzu\Users\Assets;

interface UserImageAssetInterface {
    public function isPresent(): bool;
    public function getMimeType(): string;
    public function getPublicPath(): string;
    public function getFileName(): string;
}
