<?php
namespace Misuzu;

use Aitemu\RouterRequest;

require_once __DIR__ . '/../misuzu.php';

//ob_start('ob_gzhandler');

$app = Application::getInstance();

if (isset($_COOKIE['msz_uid'], $_COOKIE['msz_sid'])) {
    $app->startSession((int)$_COOKIE['msz_uid'], $_COOKIE['msz_sid']);
}

$app->startRouter(include __DIR__ . '/../routes.php');
$app->startTemplating();

echo $app->router->resolve(
    RouterRequest::fromServer($_SERVER, $_GET, $_POST, $_COOKIE)
);
