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

define('LESS_DEST', CSS_DIR . '/%s.css');
define('TS_DEST', JS_DIR . '/%s.js');
define('DTS_DEST', TS_DIR . '/%s.d.ts');

define('NODE_MODULES_DIR', __DIR__ . '/node_modules');
define('NODE_DEST_CSS', CSS_DIR . '/libraries.css');
define('NODE_DEST_JS', JS_DIR . '/libraries.js');

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
        safe_delete($file);
        misuzu_log("Deleted '{$file}'");
    }
}

misuzu_log('Cleanup');
createDirIfNotExist(CSS_DIR);
createDirIfNotExist(JS_DIR);
deleteAllFilesInDir(CSS_DIR, '*.css');
deleteAllFilesInDir(JS_DIR, '*.js');
deleteAllFilesInDir(TS_DIR, '*.d.ts');

misuzu_log();
misuzu_log('Compiling LESS');

$styles = globDir(LESS_DIR, '*', GLOB_ONLYDIR);

foreach ($styles as $style) {
    $basename = basename($style);
    $destination = sprintf(LESS_DEST, $basename);
    $entryPoint = $style . LESS_ENTRY_POINT;
    misuzu_log("=> {$basename}");

    if (!file_exists($entryPoint)) {
        misuzu_log('==> ERR: Entry point for this style does not exist (' . basename(LESS_ENTRY_POINT) . ')');
        continue;
    }

    $compileCmd = sprintf(LESS_CMD, escapeshellarg($entryPoint), escapeshellarg($destination));
    system($compileCmd);
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
