<?php
namespace Misuzu\Users\Assets;

interface UserAssetScalableInterface {
    public function getScaledRelativePath(int $dims): string;
    public function getScaledPath(int $dims): string;
    public function isScaledPresent(int $dims): bool;
    public function deleteScaled(int $dims): void;
    public function ensureScaledExists(int $dims): void;
    public function getPublicScaledPath(int $dims): string;
    public function getScaledFileName(int $dims): string;
}
