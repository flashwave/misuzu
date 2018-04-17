<?php
namespace Misuzu;

require_once 'vendor/autoload.php';

$app = Application::start(
    __DIR__ . '/config/config.ini',
    IO\Directory::exists(__DIR__ . '/vendor/phpunit/phpunit')
);
$app->startDatabase();

if (PHP_SAPI !== 'cli') {
    $storage_dir = $app->getStoragePath();
    if (!$storage_dir->isReadable()
        || !$storage_dir->isWritable()) {
        echo 'Cannot access storage directory.';
        exit;
    }

    if (!$app->inDebugMode()) {
        ob_start('ob_gzhandler');
    }

    if ($app->config->get('Auth', 'lockdown', 'bool', false)) {
        http_response_code(503);
        $app->startTemplating();
        $app->templating->addPath('auth', __DIR__ . '/views/auth');
        echo $app->templating->render('lockdown');
        exit;
    }

    if (isset($_COOKIE['msz_uid'], $_COOKIE['msz_sid'])) {
        $app->startSession((int)$_COOKIE['msz_uid'], $_COOKIE['msz_sid']);
        $session = $app->getSession();

        if ($session !== null) {
            $session->user->last_seen = \Carbon\Carbon::now();
            $session->user->last_ip = \Misuzu\Net\IPAddress::remote();
            $session->user->save();
        }
    }

    $manage_mode = starts_with($_SERVER['REQUEST_URI'], '/manage');

    $app->startTemplating();
    $app->templating->addPath('mio', __DIR__ . '/views/mio');

    if ($manage_mode) {
        if ($app->getSession() === null || $_SERVER['HTTP_HOST'] !== 'misuzu.misaka.nl') {
            http_response_code(403);
            echo $app->templating->render('errors.403');
            exit;
        }

        $app->templating->addPath('manage', __DIR__ . '/views/manage');
    }
}
