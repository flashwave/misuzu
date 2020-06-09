<?php
namespace Misuzu;

use InvalidArgumentException;
use Twig\Environment as Twig_Environment;
use Twig_Extensions_Extension_Date;
use Twig\Loader\FilesystemLoader as Twig_Loader_Filesystem;

final class Template {
    private const FILE_EXT = '.twig';

    private static $loader;
    private static $env;
    private static $vars = [];

    public static function init(?string $cache = null, bool $debug = false): void {
        self::$loader = new Twig_Loader_Filesystem;
        self::$env = new Twig_Environment(self::$loader, [
            'cache' => $cache ?? false,
            'strict_variables' => true,
            'auto_reload' => $debug,
            'debug' => $debug,
        ]);
        self::$env->addExtension(new Twig_Extensions_Extension_Date);
        self::$env->addExtension(new TwigMisuzu);
    }

    public static function addPath(string $path): void {
        self::$loader->addPath($path);
    }

    public static function renderRaw(string $file, array $vars = []): string {
        if(!defined('MSZ_TPL_RENDER')) {
            define('MSZ_TPL_RENDER', microtime(true));
        }

        if(!ends_with($file, self::FILE_EXT)) {
            $file = str_replace('.', DIRECTORY_SEPARATOR, $file) . self::FILE_EXT;
        }

        return self::$env->render($file, array_merge(self::$vars, $vars));
    }

    public static function render(string $file, array $vars = []): void {
        echo self::renderRaw($file, $vars);
    }

    public static function set($arrayOrKey, $value = null): void {
        if(is_string($arrayOrKey)) {
            self::$vars[$arrayOrKey] = $value;
        } elseif(is_array($arrayOrKey)) {
            self::$vars = array_merge(self::$vars, $arrayOrKey);
        } else {
            throw new InvalidArgumentException('First parameter must be of type array or string.');
        }
    }
}
