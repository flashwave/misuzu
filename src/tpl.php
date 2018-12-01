<?php
use Misuzu\Twig;
use Misuzu\TwigMisuzu;

define('MSZ_TPL_FILE_EXT', '.twig');
define('MSZ_TPL_VARS_STORE', '_msz_tpl_vars');

function tpl_init(array $options = []): void
{
    $options = array_merge([
        'cache' => false,
        'strict_variables' => true,
        'auto_reload' => false,
        'debug' => false,
    ], $options);

    $GLOBALS[MSZ_TPL_VARS_STORE] = [];

    $loader = new Twig_Loader_Filesystem;
    $twig = new Twig($loader, $options);
    $twig->addExtension(new Twig_Extensions_Extension_Date);
    $twig->addExtension(new TwigMisuzu);
}

function tpl_var(string $key, $value): void
{
    $GLOBALS[MSZ_TPL_VARS_STORE][$key] = $value;
}

function tpl_vars(array $vars): void
{
    $GLOBALS[MSZ_TPL_VARS_STORE] = array_merge($GLOBALS[MSZ_TPL_VARS_STORE], $vars);
}

function tpl_add_path(string $path): void
{
    Twig::instance()->getLoader()->addPath($path);
}

function tpl_sanitise_path(string $path): string
{
    // if the .twig extension if already present just assume that the path is already correct
    if (ends_with($path, MSZ_TPL_FILE_EXT)) {
        return $path;
    }

    return str_replace('.', '/', $path) . MSZ_TPL_FILE_EXT;
}

function tpl_exists(string $path): bool
{
    return Twig::instance()->getLoader()->exists(tpl_sanitise_path($path));
}

function tpl_render(string $path, array $vars = []): string
{
    if (!defined('MSZ_TPL_RENDER')) {
        define('MSZ_TPL_RENDER', microtime(true));
    }

    $path = tpl_sanitise_path($path);

    if (count($vars)) {
        tpl_vars($vars);
    }

    return Twig::instance()->render($path, $GLOBALS[MSZ_TPL_VARS_STORE]);
}
