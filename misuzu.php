<?php
namespace Misuzu;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/colour.php';
require_once __DIR__ . '/src/zalgo.php';
require_once __DIR__ . '/src/Users/login_attempt.php';
require_once __DIR__ . '/src/Users/validation.php';

$app = new Application(
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

    if ($app->getConfig()->get('Auth', 'lockdown', 'bool', false)) {
        http_response_code(503);
        $app->startTemplating();
        $app->getTemplating()->addPath('auth', __DIR__ . '/views/auth');
        echo $app->getTemplating()->render('lockdown');
        exit;
    }

    $app->startTemplating();
    $app->getTemplating()->addPath('mio', __DIR__ . '/views/mio');

    if (isset($_COOKIE['msz_uid'], $_COOKIE['msz_sid'])) {
        $app->startSession((int)$_COOKIE['msz_uid'], $_COOKIE['msz_sid']);

        if ($app->hasActiveSession()) {
            $db = Database::connection();

            $bumpUserLast = $db->prepare('
                UPDATE `msz_users` SET
                `last_seen` = NOW(),
                `last_ip` = INET6_ATON(:last_ip)
                WHERE `user_id` = :user_id
            ');
            $bumpUserLast->bindValue('last_ip', Net\IPAddress::remote()->getString());
            $bumpUserLast->bindValue('user_id', $app->getUserId());
            $bumpUserLast->execute();

            $getUserDisplayInfo = $db->prepare('
                SELECT
                    u.`user_id`, u.`username`,
                    COALESCE(r.`role_colour`, CAST(0x40000000 AS UNSIGNED)) as `colour`
                FROM `msz_users` as u
                LEFT JOIN `msz_roles` as r
                ON u.`display_role` = r.`role_id`
                WHERE `user_id` = :user_id
            ');
            $getUserDisplayInfo->bindValue('user_id', $app->getUserId());
            $userDisplayInfo = $getUserDisplayInfo->execute() ? $getUserDisplayInfo->fetch() : [];
            $app->getTemplating()->var('current_user', $userDisplayInfo);
        }
    }

    $manage_mode = starts_with($_SERVER['REQUEST_URI'], '/manage');

    if ($manage_mode) {
        if ($app->getUserId() !== 1) {
            http_response_code(403);
            echo $app->getTemplating()->render('errors.403');
            exit;
        }

        $app->getTemplating()->addPath('manage', __DIR__ . '/views/manage');
    }
}
