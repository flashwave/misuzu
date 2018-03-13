<?php
namespace Misuzu;

use Phroute\Phroute\Dispatcher;

require_once __DIR__ . '/../misuzu.php';

//ob_start('ob_gzhandler');

$app = Application::getInstance();

if (isset($_COOKIE['msz_uid'], $_COOKIE['msz_sid'])) {
    $app->startSession((int)$_COOKIE['msz_uid'], $_COOKIE['msz_sid']);
}

$app->startRouter();
$app->startTemplating();

include __DIR__ . '/../routes.php';

echo (new Dispatcher($app->router->getData()))->dispatch(
    $_SERVER['REQUEST_METHOD'],
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);
