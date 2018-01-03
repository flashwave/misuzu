<?php
namespace Misuzu;

require_once 'vendor/autoload.php';

$app = Application::start(__DIR__ . '/config/config.ini');
$app->debug(IO\Directory::exists(__DIR__ . '/vendor/phpunit/phpunit'));
$app->startDatabase();
