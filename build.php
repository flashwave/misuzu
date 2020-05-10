<?php
/**
 * Misuzu Asset Build Script.
 */

/**
 * BEYOND THIS POINT YOU WON'T HAVE TO EDIT THE CONFIG PRETTY MUCH EVER
 */

define('ASSETS_DIR', __DIR__ . '/assets');
define('TS_DIR', ASSETS_DIR . '/typescript');

define('PUBLIC_DIR', __DIR__ . '/public');
define('JS_DIR', PUBLIC_DIR . '/js');

define('TWIG_DIRECTORY', sys_get_temp_dir() . '/msz-tpl-cache-' . md5(__DIR__));

/**
 * FUNCTIONS
 */

function build_log(string $text = ''): void {
    echo strlen($text) > 0 ? date('<H:i:s> ') . $text . PHP_EOL : PHP_EOL;
}

function create_dir(string $dir): void {
    if(!file_exists($dir) || !is_dir($dir)) {
        mkdir($dir);
        build_log("Created '{$dir}'!");
    }
}

function glob_dir(string $dir, string $pattern, int $flags = 0): array {
    return glob($dir . '/' . $pattern, $flags);
}

function purge_dir(string $dir, string $pattern): void {
    $files = glob_dir($dir, $pattern);

    foreach($files as $file) {
        if(is_dir($file)) {
            build_log("'{$file}' is a directory, entering...");
            purge_dir($file, $pattern);
            rmdir($file);
        } else {
            unlink($file);
            build_log("Deleted '{$file}'");
        }
    }
}

$doAll = empty($argv[1]) || $argv[1] === 'all';
$doJs = $doAll || $argv[1] === 'js';

// Make sure we're running from the misuzu root directory.
chdir(__DIR__);

build_log('Cleanup');

if($doJs) {
    create_dir(JS_DIR);
    purge_dir(JS_DIR, '*.js');
    purge_dir(TS_DIR, '*.d.ts');

    build_log();
    build_log('Compiling TypeScript');
    build_log(shell_exec('tsc --extendedDiagnostics -p tsconfig.json'));
}

// no need to do this in debug mode, auto reload is enabled and cache is disabled
if($doAll && !file_exists(__DIR__ . '/.debug')) {
    // Clear Twig cache
    build_log();
    build_log('Deleting template cache');
    purge_dir(TWIG_DIRECTORY, '*');
}
