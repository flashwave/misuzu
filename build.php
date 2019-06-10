<?php
/**
 * Misuzu Asset Build Script.
 */

/**
 * NPM module files to be imported into /js/libraries.js
 */
define('NODE_IMPORT_JS', [
    'highlightjs/highlight.pack.min.js',
    'timeago.js/dist/timeago.min.js',
    'timeago.js/dist/timeago.locales.min.js',
]);

/**
 * NPM module files to be imported into /css/libraries.css
 */
define('NODE_IMPORT_CSS', [
    'highlightjs/styles/default.css',
    'highlightjs/styles/tomorrow-night.css',
    '@fortawesome/fontawesome-free/css/all.min.css',
]);

/**
 * Directories to copy to the public folder
 */
define('NODE_COPY_DIRECTORY', [
    '@fortawesome/fontawesome-free/webfonts' => 'webfonts',
]);

/**
 * BEYOND THIS POINT YOU WON'T HAVE TO EDIT THE CONFIG PRETTY MUCH EVER
 */

define('LESS_CMD', 'lessc --verbose %s %s');

define('ASSETS_DIR', __DIR__ . '/assets');
define('LESS_DIR', ASSETS_DIR . '/less');
define('TS_DIR', ASSETS_DIR . '/typescript');

define('LESS_ENTRY_POINT', '/main.less');

define('PUBLIC_DIR', __DIR__ . '/public');
define('CSS_DIR', PUBLIC_DIR . '/css');
define('JS_DIR', PUBLIC_DIR . '/js');

define('LESS_DEST', CSS_DIR . '/style.css');
define('TS_DEST', JS_DIR . '/misuzu.js');
define('TS_SRC', JS_DIR . '/misuzu');

define('NODE_MODULES_DIR', __DIR__ . '/node_modules');
define('NODE_DEST_CSS', CSS_DIR . '/libraries.css');
define('NODE_DEST_JS', JS_DIR . '/libraries.js');

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

function recursive_copy(string $source, string $dest): bool {
    if(is_link($source)) {
        return symlink(readlink($source), $dest);
    }

    if(is_file($source)) {
        return copy($source, $dest);
    }

    if(!is_dir($dest)) {
        mkdir($dest);
    }

    $dir = dir($source);

    while(($entry = $dir->read()) !== false) {
        if($entry === '.' || $entry === '..') {
            continue;
        }

        recursive_copy($source . DIRECTORY_SEPARATOR . $entry, $dest . DIRECTORY_SEPARATOR . $entry);
    }

    $dir->close();
    return true;
}

$doAll = empty($argv[1]) || $argv[1] === 'all';
$doCss = $doAll || $argv[1] === 'css';
$doJs = $doAll || $argv[1] === 'js';

// Make sure we're running from the misuzu root directory.
chdir(__DIR__);

build_log('Cleanup');

if($doCss) {
    create_dir(CSS_DIR);
    purge_dir(CSS_DIR, '*.css');
}

if($doJs) {
    create_dir(JS_DIR);
    purge_dir(JS_DIR, '*.js');
    purge_dir(TS_DIR, '*.d.ts');
}

if($doCss) {
    build_log();
    build_log('Compiling LESS');

    if(!is_file(LESS_DIR . LESS_ENTRY_POINT)) {
        build_log('==> ERR: Entry point for this style does not exist (' . basename(LESS_ENTRY_POINT) . ')');
    } else {
        system(sprintf(LESS_CMD, escapeshellarg(LESS_DIR . LESS_ENTRY_POINT), LESS_DEST));
    }
}

build_log();
build_log('Importing libraries');

define('IMPORT_SEQ', [
    [
        'name' => 'CSS',
        'files' => NODE_IMPORT_CSS,
        'destination' => NODE_DEST_CSS,
        'insert-semicolon' => false,
        'do' => $doCss,
    ],
    [
        'name' => 'JavaScript',
        'files' => NODE_IMPORT_JS,
        'destination' => NODE_DEST_JS,
        'insert-semicolon' => true,
        'do' => $doJs,
    ],
]);

foreach(IMPORT_SEQ as $sequence) {
    if(!$sequence['do']) {
        continue;
    }

    build_log("=> {$sequence['name']}");

    $contents = '';

    foreach($sequence['files'] as $file) {
        $realpath = realpath(NODE_MODULES_DIR . '/' . $file);

        build_log("==> '{$file}'");

        if(!file_exists($realpath)) {
            build_log('===> File does not exist.');
            continue;
        }

        $contents .= file_get_contents($realpath);

        if($sequence['insert-semicolon']) {
            $contents .= ';';
        }
    }

    file_put_contents($sequence['destination'], $contents);
}

if($doJs) {
    build_log();
    build_log('Compiling TypeScript');
    build_log(shell_exec('tsc --extendedDiagnostics -p tsconfig.json'));
}

build_log();
build_log('Copying data...');

foreach(NODE_COPY_DIRECTORY as $source => $dest) {
    build_log("=> " . basename($dest));
    $source = realpath(NODE_MODULES_DIR . DIRECTORY_SEPARATOR . $source);
    $dest = PUBLIC_DIR . DIRECTORY_SEPARATOR . $dest;
    purge_dir($dest, '*');
    recursive_copy($source, $dest);
}

// no need to do this in debug mode, auto reload is enabled and cache is disabled
if($doAll && !file_exists(__DIR__ . '/.debug')) {
    // Clear Twig cache
    build_log();
    build_log('Deleting template cache');
    purge_dir(TWIG_DIRECTORY, '*');
}
