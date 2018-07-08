<?php
namespace Misuzu;

date_default_timezone_set('UTC');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/changelog.php';
require_once __DIR__ . '/src/colour.php';
require_once __DIR__ . '/src/manage.php';
require_once __DIR__ . '/src/news.php';
require_once __DIR__ . '/src/perms.php';
require_once __DIR__ . '/src/zalgo.php';
require_once __DIR__ . '/src/Forum/forum.php';
require_once __DIR__ . '/src/Forum/post.php';
require_once __DIR__ . '/src/Forum/topic.php';
require_once __DIR__ . '/src/Forum/validate.php';
require_once __DIR__ . '/src/Users/login_attempt.php';
require_once __DIR__ . '/src/Users/profile.php';
require_once __DIR__ . '/src/Users/role.php';
require_once __DIR__ . '/src/Users/session.php';
require_once __DIR__ . '/src/Users/user.php';
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

    $app->startTemplating();
    $tpl = $app->getTemplating();

    if ($app->getConfig()->get('Auth', 'lockdown', 'bool', false)) {
        http_response_code(503);
        $tpl->addPath('auth', __DIR__ . '/views/auth');
        echo $tpl->render('lockdown');
        exit;
    }

    $tpl->addPath('mio', __DIR__ . '/views/mio');

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
            $tpl->var('current_user', $userDisplayInfo);
        }
    }

    $inManageMode = starts_with($_SERVER['REQUEST_URI'], '/manage');
    $hasManageAccess = perms_check(perms_get_user(MSZ_PERMS_USER, $app->getUserId()), MSZ_USER_PERM_CAN_MANAGE);
    $tpl->var('has_manage_access', $hasManageAccess);

    if ($inManageMode) {
        if (!$hasManageAccess) {
            echo render_error(403);
            exit;
        }

        $tpl = $app->getTemplating();
        $tpl->var('manage_menu', manage_get_menu($app->getUserId()));
        $tpl->addPath('manage', __DIR__ . '/views/manage');
    }
}
