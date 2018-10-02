<?php
namespace Misuzu;

define('MSZ_STARTUP', microtime(true));
define('MSZ_DEBUG', file_exists(__DIR__ . '/.debug'));

error_reporting(MSZ_DEBUG ? -1 : 0);
ini_set('display_errors', MSZ_DEBUG ? 'On' : 'Off');

date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__);

require_once 'vendor/autoload.php';

$errorHandler = new \Whoops\Run;
$errorHandler->pushHandler(
    MSZ_DEBUG
    ? (
        PHP_SAPI === 'cli'
        ? new \Whoops\Handler\PlainTextHandler
        : new \Whoops\Handler\PrettyPageHandler
    )
    : ($errorReporter = new WhoopsReporter)
);
$errorHandler->register();

require_once 'src/array.php';
require_once 'src/audit_log.php';
require_once 'src/changelog.php';
require_once 'src/colour.php';
require_once 'src/comments.php';
require_once 'src/csrf.php';
require_once 'src/general.php';
require_once 'src/git.php';
require_once 'src/manage.php';
require_once 'src/news.php';
require_once 'src/perms.php';
require_once 'src/string.php';
require_once 'src/tpl.php';
require_once 'src/zalgo.php';
require_once 'src/Forum/forum.php';
require_once 'src/Forum/perms.php';
require_once 'src/Forum/post.php';
require_once 'src/Forum/topic.php';
require_once 'src/Forum/validate.php';
require_once 'src/Net/cidr.php';
require_once 'src/Net/ip.php';
require_once 'src/Parsers/parse.php';
require_once 'src/Users/login_attempt.php';
require_once 'src/Users/profile.php';
require_once 'src/Users/relations.php';
require_once 'src/Users/role.php';
require_once 'src/Users/session.php';
require_once 'src/Users/user.php';
require_once 'src/Users/validation.php';

$app = new Application(__DIR__ . '/config/config.ini');

if (!empty($errorReporter)) {
    $errorReporter->setReportInfo(...$app->getReportInfo());
}

$app->startDatabase();

if (PHP_SAPI === 'cli') {
    if ($argv[0] === basename(__FILE__)) {
        switch ($argv[1] ?? null) {
            case 'cron':
                // Ensure main role exists.
                Database::exec("
                    INSERT IGNORE INTO `msz_roles`
                        (`role_id`, `role_name`, `role_hierarchy`, `role_colour`, `role_description`, `created_at`)
                    VALUES
                        (1, 'Member', 1, 1073741824, NULL, NOW())
                ");

                // Ensures all users are in the main role.
                Database::exec('
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
                Database::exec('
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

                // Deletes expired sessions
                Database::exec('
                    DELETE FROM `msz_sessions`
                    WHERE `expires_on` < NOW()
                ');

                // Remove old password reset records, left for a week for possible review
                Database::exec('
                    DELETE FROM `msz_users_password_resets`
                    WHERE `reset_requested` < NOW() - INTERVAL 1 WEEK
                ');

                // Cleans up the login history table
                Database::exec('
                    DELETE FROM `msz_login_attempts`
                    WHERE `created_at` < NOW() - INTERVAL 1 YEAR
                ');

                // Cleans up the audit log table
                Database::exec('
                    DELETE FROM `msz_audit_log`
                    WHERE `log_created` < NOW() - INTERVAL 1 YEAR
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

            case 'new-mig':
                if (empty($argv[2])) {
                    echo 'Specify a migration name.' . PHP_EOL;
                    return;
                }

                if (!preg_match('#^([a-z_]+)$#', $argv[2])) {
                    echo 'Migration name may only contain alpha and _ characters.' . PHP_EOL;
                    return;
                }

                $filename = date('Y_m_d_His_') . trim($argv[2], '_') . '.php';
                $filepath = __DIR__ . '/database/' . $filename;
                $namespace = snake_to_camel($argv[2]);
                $template = <<<MIG
<?php
namespace Misuzu\DatabaseMigrations\\$namespace;

use PDO;

function migrate_up(PDO \$conn): void
{
    \$conn->exec('
        CREATE TABLE ...
    ');
}

function migrate_down(PDO \$conn): void
{
    \$conn->exec('DROP TABLE ...');
}

MIG;

                file_put_contents($filepath, $template);

                echo "Template for '{$namespace}' has been created." . PHP_EOL;
                break;

            default:
                echo 'Unknown command.' . PHP_EOL;
                break;
        }
    }
} else {
    if (!MSZ_DEBUG) {
        ob_start('ob_gzhandler');
    }

    // we're running this again because ob_clean breaks gzip otherwise
    ob_start();

    if (!$app->canAccessStorage()) {
        echo 'Cannot access storage directory.';
        exit;
    }

    $app->startCache();

    tpl_init([
        'debug' => MSZ_DEBUG,
        'auto_reload' => MSZ_DEBUG,
        'cache' => MSZ_DEBUG ? false : create_directory(build_path(sys_get_temp_dir(), 'msz-tpl-cache-' . md5(__DIR__))),
    ]);

    tpl_var('globals', $app->getSiteInfo());

    tpl_add_function('json_decode', true);
    tpl_add_function('byte_symbol', true);
    tpl_add_function('html_link', true);
    tpl_add_function('html_colour', true);
    tpl_add_function('url_construct', true);
    tpl_add_function('country_name', true, 'get_country_name');
    tpl_add_function('flip', true, 'array_flip');
    tpl_add_function('first_paragraph', true);
    tpl_add_function('colour_get_css', true);
    tpl_add_function('colour_get_css_contrast', true);
    tpl_add_function('colour_get_inherit', true);
    tpl_add_function('colour_get_red', true);
    tpl_add_function('colour_get_green', true);
    tpl_add_function('colour_get_blue', true);
    tpl_add_function('parse_line', true);
    tpl_add_function('parse_text', true);
    tpl_add_function('asset_url', true);
    tpl_add_function('vsprintf', true);
    tpl_add_function('perms_check', true);
    tpl_add_function('bg_settings', true, 'user_background_settings_strings');
    tpl_add_function('csrf', true, 'csrf_html');

    tpl_add_function('git_commit_hash');
    tpl_add_function('git_branch');
    tpl_add_function('csrf_token');
    tpl_add_function('startup_time', false, function (float $time = MSZ_STARTUP) {
        return microtime(true) - $time;
    });
    tpl_add_function('sql_query_count', false, [Database::class, 'queryCount']);

    tpl_add_path(__DIR__ . '/templates');

    $misuzuBypassLockdown = !empty($misuzuBypassLockdown);

    if (!$misuzuBypassLockdown && $app->underLockdown()) {
        http_response_code(503);
        echo tpl_render('auth.lockdown');
        exit;
    }

    if (isset($_COOKIE['msz_uid'], $_COOKIE['msz_sid'])
        && user_session_start((int)$_COOKIE['msz_uid'], $_COOKIE['msz_sid'])) {
        $mszUserId = (int)$_COOKIE['msz_uid'];

        user_bump_last_active($mszUserId);

        $getUserDisplayInfo = Database::prepare('
            SELECT
                u.`user_id`, u.`username`, u.`user_background_settings`,
                COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`
            FROM `msz_users` as u
            LEFT JOIN `msz_roles` as r
            ON u.`display_role` = r.`role_id`
            WHERE `user_id` = :user_id
        ');
        $getUserDisplayInfo->bindValue('user_id', $mszUserId);
        $userDisplayInfo = $getUserDisplayInfo->execute() ? $getUserDisplayInfo->fetch() : [];
    }

    csrf_init($app->getCsrfSecretKey(), empty($userDisplayInfo) ? ip_remote_address() : $_COOKIE['msz_sid']);

    $privateInfo = $app->getPrivateInfo();

    if (!$misuzuBypassLockdown && $privateInfo['enabled']) {
        if (user_session_active()) {
            $generalPerms = perms_get_user(MSZ_PERMS_GENERAL, $userDisplayInfo['user_id']);

            if ($privateInfo['permission'] && !perms_check($generalPerms, $privateInfo['permission'])) {
                unset($userDisplayInfo);
                user_session_stop(); // au revoir
            }
        } else {
            http_response_code(401);
            echo tpl_render('auth.private', [
                'private_info'=> $privateInfo,
            ]);
            exit;
        }
    }

    if (!empty($userDisplayInfo)) {
        tpl_var('current_user', $userDisplayInfo);
    }

    $inManageMode = starts_with($_SERVER['REQUEST_URI'], '/manage');
    $hasManageAccess = perms_check(perms_get_user(MSZ_PERMS_GENERAL, $userDisplayInfo['user_id'] ?? 0), MSZ_PERM_GENERAL_CAN_MANAGE);
    tpl_var('has_manage_access', $hasManageAccess);

    if ($inManageMode) {
        if (!$hasManageAccess) {
            echo render_error(403);
            exit;
        }

        tpl_var('manage_menu', manage_get_menu($userDisplayInfo['user_id'] ?? 0));
    }
}
