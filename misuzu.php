<?php
namespace Misuzu;

require_once 'vendor/autoload.php';

$app = Application::start(
    __DIR__ . '/config/config.ini',
    IO\Directory::exists(__DIR__ . '/vendor/phpunit/phpunit')
);
$app->startDatabase();

if (PHP_SAPI !== 'cli') {
    if (isset($_COOKIE['msz_uid'], $_COOKIE['msz_sid'])) {
        $app->startSession((int)$_COOKIE['msz_uid'], $_COOKIE['msz_sid']);
    }

    //ob_start('ob_gzhandler');

    $app->startTemplating();
}
