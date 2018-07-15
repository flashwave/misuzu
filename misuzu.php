<?php
namespace Misuzu;

date_default_timezone_set('UTC');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/changelog.php';
require_once __DIR__ . '/src/colour.php';
require_once __DIR__ . '/src/comments.php';
require_once __DIR__ . '/src/general.php';
require_once __DIR__ . '/src/git.php';
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

if (PHP_SAPI === 'cli') {
    if ($argv[0] === basename(__FILE__)) {
        switch ($argv[1] ?? null) {
            case 'cron':
                $db = Database::connection();

                // Ensure main role exists.
                $db->query("
                    INSERT IGNORE INTO `msz_roles`
                        (`role_id`, `role_name`, `role_hierarchy`, `role_colour`, `role_description`, `created_at`)
                    VALUES
                        (1, 'Member', 1, 1073741824, NULL, NOW())
                ");

                // Ensures all users are in the main role.
                $db->query('
                    INSERT INTO `msz_user_roles`
                        (`user_id`, `role_id`)
                    SELECT `user_id`, 1 FROM `msz_users` as u
                    WHERE NOT EXISTS (
                        SELECT 1
                        FROM `msz_user_roles` as ur
                        WHERE `role_id` = 1
                        AND u.`user_id` = ur.`user_id`
                    )
                ');

                // Ensures all display_role values are correct with `msz_user_roles`
                $db->query('
                    UPDATE `msz_users` as u
                    SET `display_role` = (
                         SELECT ur.`role_id`
                         FROM `msz_user_roles` as ur
                         LEFT JOIN `msz_roles` as r
                         ON r.`role_id` = ur.`role_id`
                         WHERE ur.`user_id` = u.`user_id`
                         ORDER BY `role_hierarchy` DESC
                         LIMIT 1
                    )
                    WHERE NOT EXISTS (
                        SELECT 1
                        FROM `msz_user_roles` as ur
                        WHERE ur.`role_id` = u.`display_role`
                        AND `ur`.`user_id` = u.`user_id`
                    )
                ');
                break;

            case 'migrate':
                $migrationTargets = [
                'mysql-main' => __DIR__ . '/database',
                ];
                $doRollback = !empty($argv[2]) && $argv[2] === 'rollback';
                $targetDb = isset($argv[$doRollback ? 3 : 2]) ? $argv[$doRollback ? 3 : 2] : null;

                if ($targetDb !== null && !array_key_exists($targetDb, $migrationTargets)) {
                    echo 'Invalid target database connection.' . PHP_EOL;
                    break;
                }

                foreach ($migrationTargets as $db => $path) {
                    echo "Creating migration manager for '{$db}'..." . PHP_EOL;
                    $migrationManager = new DatabaseMigrationManager(Database::connection($db), $path);
                    $migrationManager->setLogger(function ($log) {
                        echo $log . PHP_EOL;
                    });

                    if ($doRollback) {
                        echo "Rolling back last migrations for '{$db}'..." . PHP_EOL;
                        $migrationManager->rollback();
                    } else {
                        echo "Running migrations for '{$db}'..." . PHP_EOL;
                        $migrationManager->migrate();
                    }

                    $errors = $migrationManager->getErrors();
                    $errorCount = count($errors);

                    if ($errorCount < 1) {
                        echo 'Completed with no errors!' . PHP_EOL;
                    } else {
                        echo PHP_EOL . "There were {$errorCount} errors during the migrations..." . PHP_EOL;

                        foreach ($errors as $error) {
                            echo $error . PHP_EOL;
                        }
                    }
                }
                break;

            default:
                echo 'Unknown command.' . PHP_EOL;
                break;
        }
    }
} else {
    ob_start($app->inDebugMode() ? null : 'ob_gzhandler');

    $storage_dir = $app->getStoragePath();
    if (!$storage_dir->isReadable()
        || !$storage_dir->isWritable()) {
        echo 'Cannot access storage directory.';
        exit;
    }

    $app->startCache();
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
    $hasManageAccess = perms_check(perms_get_user(MSZ_PERMS_GENERAL, $app->getUserId()), MSZ_GENERAL_PERM_CAN_MANAGE);
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
