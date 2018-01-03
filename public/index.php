<?php
namespace Misuzu;

use Aitemu\RouterRequest;

require_once __DIR__ . '/../misuzu.php';

ob_start('ob_gzhandler');

echo Application::getInstance()->router->resolve(
    RouterRequest::fromServer($_SERVER, $_GET, $_POST, $_COOKIE)
);
