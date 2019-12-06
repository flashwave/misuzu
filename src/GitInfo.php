<?php
namespace Misuzu;

final class GitInfo {
    private const FORMAT_HASH_SHORT = '%h';
    private const FORMAT_HASH_LONG = '%H';

    public static function log(string $format, string $args = ''): string {
        return trim(shell_exec(sprintf('git log --pretty="%s" %s -n1 HEAD', $format, $args)));
    }

    public static function hash(bool $long = false): string {
        return self::log($long ? self::FORMAT_HASH_LONG : self::FORMAT_HASH_SHORT);
    }

    public static function branch(): string {
        return trim(`git rev-parse --abbrev-ref HEAD`);
    }

    public static function tag(): string {
        return trim(`git describe --abbrev=0 --tags`);
    }
}
