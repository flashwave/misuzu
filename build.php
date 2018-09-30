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
define('TS_DEST', JS_DIR . '/%s.js');
define('DTS_DEST', TS_DIR . '/%s.d.ts');

define('NODE_MODULES_DIR', __DIR__ . '/node_modules');
define('NODE_DEST_CSS', CSS_DIR . '/libraries.css');
define('NODE_DEST_JS', JS_DIR . '/libraries.js');

define('TWIG_DIRECTORY', sys_get_temp_dir() . '/msz-tpl-cache-' . md5(__DIR__));

/**
 * FUNCTIONS
 */

function misuzu_log(string $text = ''): void
{
    echo strlen($text) > 0 ? date('<H:i:s> ') . $text . PHP_EOL : PHP_EOL;
}

function createDirIfNotExist(string $dir): void
{
    if (!file_exists($dir) || !is_dir($dir)) {
        mkdir($dir);
        misuzu_log("Created '{$dir}'!");
    }
}

function globDir(string $dir, string $pattern, int $flags = 0): array
{
    return glob($dir . '/' . $pattern, $flags);
}

function deleteAllFilesInDir(string $dir, string $pattern): void
{
    $files = globDir($dir, $pattern);

    foreach ($files as $file) {
        if (is_dir($file)) {
            misuzu_log("'{$file}' is a directory, entering...");
            deleteAllFilesInDir($file, $pattern);
            rmdir($file);
        } else {
            unlink($file);
            misuzu_log("Deleted '{$file}'");
        }
    }
}

function recursiveCopy(string $source, string $dest): bool
{
    if (is_link($source)) {
        return symlink(readlink($source), $dest);
    }

    if (is_file($source)) {
        return copy($source, $dest);
    }

    if (!is_dir($dest)) {
        mkdir($dest);
    }

    $dir = dir($source);

    while (($entry = $dir->read()) !== false) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        recursiveCopy($source . DIRECTORY_SEPARATOR . $entry, $dest . DIRECTORY_SEPARATOR . $entry);
    }

    $dir->close();
    return true;
}

misuzu_log('Cleanup');
createDirIfNotExist(CSS_DIR);
createDirIfNotExist(JS_DIR);
deleteAllFilesInDir(CSS_DIR, '*.css');
deleteAllFilesInDir(JS_DIR, '*.js');
deleteAllFilesInDir(TS_DIR, '*.d.ts');

misuzu_log();
misuzu_log('Compiling LESS');

if (!is_file(LESS_DIR . LESS_ENTRY_POINT)) {
    misuzu_log('==> ERR: Entry point for this style does not exist (' . basename(LESS_ENTRY_POINT) . ')');
} else {
    system(sprintf(LESS_CMD, escapeshellarg(LESS_DIR . LESS_ENTRY_POINT), LESS_DEST));
}

// figure this out
//misuzu_log();
//misuzu_log('Compiling TypeScript');

misuzu_log();
misuzu_log('Importing libraries');

define('IMPORT_SEQ', [
    [
        'name' => 'CSS',
        'files' => NODE_IMPORT_CSS,
        'destination' => NODE_DEST_CSS,
        'insert-semicolon' => false,
    ],
    [
        'name' => 'JavaScript',
        'files' => NODE_IMPORT_JS,
        'destination' => NODE_DEST_JS,
        'insert-semicolon' => true,
    ],
]);

foreach (IMPORT_SEQ as $sequence) {
    misuzu_log("=> {$sequence['name']}");

    $contents = '';

    foreach ($sequence['files'] as $file) {
        $realpath = realpath(NODE_MODULES_DIR . '/' . $file);

        misuzu_log("==> '{$file}'");

        if (!file_exists($realpath)) {
            misuzu_log('===> File does not exist.');
            continue;
        }

        $contents .= file_get_contents($realpath);

        if ($sequence['insert-semicolon']) {
            $contents .= ';';
        }
    }

    file_put_contents($sequence['destination'], $contents);
}

misuzu_log();
misuzu_log('Copying data...');

foreach (NODE_COPY_DIRECTORY as $source => $dest) {
    misuzu_log("=> " . basename($dest));
    $source = realpath(NODE_MODULES_DIR . DIRECTORY_SEPARATOR . $source);
    $dest = PUBLIC_DIR . DIRECTORY_SEPARATOR . $dest;
    deleteAllFilesInDir($dest, '*');
    recursiveCopy($source, $dest);
}

// no need to do this in debug mode, auto reload is enabled and cache is disabled
if (!file_exists(__DIR__ . '/.debug')) {
    // Clear Twig cache
    misuzu_log();
    misuzu_log('Deleting template cache');
    deleteAllFilesInDir(TWIG_DIRECTORY, '*');
}
