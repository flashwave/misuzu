<?php
namespace Misuzu\Http\Filters;

final class Filter {
    public static function call(string $name): array {
        [$funcName, $className] = explode('@', $name, 2);
        return [__NAMESPACE__ . '\\' . $className, $funcName];
    }
}
