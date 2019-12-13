<?php
namespace Misuzu\Http\Handlers;

abstract class Handler {
    public static function call(string $name): array {
        [$funcName, $className] = explode('@', $name, 2);
        return [__NAMESPACE__ . '\\' . $className, $funcName];
    }

    public static function redirect(string $location, bool $permanent = false): callable {
        return function (Response $resp) use ($location, $permanent): void {
            $resp->redirect($location, $permanent);
        };
    }
}
