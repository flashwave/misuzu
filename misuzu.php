<?php
namespace Misuzu;

require_once 'vendor/autoload.php';

Application::start()->debug(IO\Directory::exists(__DIR__ . '/vendor/phpunit/phpunit'));
