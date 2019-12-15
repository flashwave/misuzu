<?php
namespace Misuzu\Http\Filters;

final class Filter {
    public static function call(string $className): string {
        return __NAMESPACE__ . '\\' . $className . 'Filter';
    }
}
