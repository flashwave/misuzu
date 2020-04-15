<?php
namespace Misuzu;

use PDO;
use Misuzu\Database\Database;
use Misuzu\Database\DatabaseMigrationManager;
use Misuzu\Net\GeoIP;
use Misuzu\Net\IPAddress;

define('MSZ_STARTUP', microtime(true));
define('MSZ_ROOT', __DIR__);
define('MSZ_CLI', PHP_SAPI === 'cli');
define('MSZ_DEBUG', is_file(MSZ_ROOT . '/.debug'));
define('MSZ_PHP_MIN_VER', '7.4.0');

if(version_compare(PHP_VERSION, MSZ_PHP_MIN_VER, '<')) {
    die('Misuzu requires <i>at least</i> PHP <b>' . MSZ_PHP_MIN_VER . '</b> to run.');
}

error_reporting(MSZ_DEBUG ? -1 : 0);
ini_set('display_errors', MSZ_DEBUG ? 'On' : 'Off');

mb_internal_encoding('UTF-8');
date_default_timezone_set('UTC');
set_include_path(get_include_path() . PATH_SEPARATOR . MSZ_ROOT);

require_once 'vendor/autoload.php';

class_alias(\Misuzu\Http\HttpServerRequestMessage::class, '\Misuzu\Http\Handlers\Request');
class_alias(\Misuzu\Http\Routing\RouterResponseMessage::class, '\Misuzu\Http\Handlers\Response');

$errorHandler = new \Whoops\Run;
$errorHandler->pushHandler(
    MSZ_CLI
    ? new \Whoops\Handler\PlainTextHandler
    : (
        MSZ_DEBUG
        ? new \Whoops\Handler\PrettyPageHandler
        : ($errorReporter = new WhoopsReporter)
    )
);
$errorHandler->register();

require_once 'src/audit_log.php';
require_once 'src/changelog.php';
require_once 'src/comments.php';
require_once 'src/manage.php';
require_once 'src/news.php';
require_once 'src/perms.php';
require_once 'src/url.php';
require_once 'src/Forum/forum.php';
require_once 'src/Forum/leaderboard.php';
require_once 'src/Forum/perms.php';
require_once 'src/Forum/poll.php';
require_once 'src/Forum/post.php';
require_once 'src/Forum/topic.php';
require_once 'src/Forum/validate.php';
require_once 'src/Users/auth.php';
require_once 'src/Users/avatar.php';
require_once 'src/Users/background.php';
require_once 'src/Users/login_attempt.php';
require_once 'src/Users/object.php';
require_once 'src/Users/recovery.php';
require_once 'src/Users/relations.php';
require_once 'src/Users/role.php';
require_once 'src/Users/session.php';
require_once 'src/Users/user.php';
require_once 'src/Users/validation.php';
require_once 'src/Users/warning.php';

$dbConfig = parse_ini_file(MSZ_ROOT . '/config/config.ini', true, INI_SCANNER_TYPED);

if(empty($dbConfig)) {
    echo 'Database config is missing.';
    exit;
}

$dbConfig = $dbConfig['Database'] ?? $dbConfig['Database.mysql-main'] ?? [];

DB::init(DB::buildDSN($dbConfig), $dbConfig['username'] ?? '', $dbConfig['password'] ?? '', DB::ATTRS);

Config::init();
Mailer::init(Config::get('mail.method', Config::TYPE_STR), [
    'host'        => Config::get('mail.host',           Config::TYPE_STR),
    'port'        => Config::get('mail.port',           Config::TYPE_INT, 25),
    'username'    => Config::get('mail.username',       Config::TYPE_STR),
    'password'    => Config::get('mail.password',       Config::TYPE_STR),
    'encryption'  => Config::get('mail.encryption',     Config::TYPE_STR),
    'sender_name' => Config::get('mail.sender.name',    Config::TYPE_STR),
    'sender_addr' => Config::get('mail.sender.address', Config::TYPE_STR),
]);

if(!empty($errorReporter)) {
    $errorReporter->setReportInfo(
        Config::get('error_report.url', Config::TYPE_STR),
        Config::get('error_report.secret', Config::TYPE_STR)
    );
}

// replace this with a better storage mechanism
define('MSZ_STORAGE', Config::get('storage.path', Config::TYPE_STR, MSZ_ROOT . '/store'));
mkdirs(MSZ_STORAGE, true);

if(MSZ_CLI) {
    if(realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
        switch($argv[1] ?? null) {
            case 'cron':
                $runLowFreq = (bool)(!empty($argv[2]) && $argv[2] == 'low');

                $cronTasks = [
                    [
                        'name' => 'Ensures main role exists.',
                        'type' => 'sql',
                        'run' => $runLowFreq,
                        'command' => "
                            INSERT IGNORE INTO `msz_roles`
                                (`role_id`, `role_name`, `role_hierarchy`, `role_colour`, `role_description`, `role_created`)
                            VALUES
                                (1, 'Member', 1, 1073741824, NULL, NOW())
                        ",
                    ],
                    [
                        'name' => 'Ensures all users are in the main role.',
                        'type' => 'sql',
                        'run' => $runLowFreq,
                        'command' => "
                            INSERT INTO `msz_user_roles`
                                (`user_id`, `role_id`)
                            SELECT `user_id`, 1 FROM `msz_users` as u
                            WHERE NOT EXISTS (
                                SELECT 1
                                FROM `msz_user_roles` as ur
                                WHERE `role_id` = 1
                                AND u.`user_id` = ur.`user_id`
                            )
                        ",
                    ],
                    [
                        'name' => 'Ensures all display_role values are correct with `msz_user_roles`.',
                        'type' => 'sql',
                        'run' => $runLowFreq,
                        'command' => "
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
                        ",
                    ],
                    [
                        'name' => 'Remove expired sessions.',
                        'type' => 'sql',
                        'run' => true,
                        'command' => "
                            DELETE FROM `msz_sessions`
                            WHERE `session_expires` < NOW()
                        ",
                    ],
                    [
                        'name' => 'Remove old password reset records.',
                        'type' => 'sql',
                        'run' => true,
                        'command' => "
                            DELETE FROM `msz_users_password_resets`
                            WHERE `reset_requested` < NOW() - INTERVAL 1 WEEK
                        ",
                    ],
                    [
                        'name' => 'Remove old chat login tokens.',
                        'type' => 'sql',
                        'run' => true,
                        'command' => "
                            DELETE FROM `msz_user_chat_tokens`
                            WHERE `token_created` < NOW() - INTERVAL 1 WEEK
                        ",
                    ],
                    [
                        'name' => 'Clean up login history.',
                        'type' => 'sql',
                        'run' => true,
                        'command' => "
                            DELETE FROM `msz_login_attempts`
                            WHERE `attempt_created` < NOW() - INTERVAL 1 MONTH
                        ",
                    ],
                    [
                        'name' => 'Clean up audit log.',
                        'type' => 'sql',
                        'run' => true,
                        'command' => "
                            DELETE FROM `msz_audit_log`
                            WHERE `log_created` < NOW() - INTERVAL 3 MONTH
                        ",
                    ],
                    [
                        'name' => 'Remove stale forum tracking entries.',
                        'type' => 'sql',
                        'run' => true,
                        'command' => "
                            DELETE tt FROM `msz_forum_topics_track` as tt
                            LEFT JOIN `msz_forum_topics` as t
                            ON t.`topic_id` = tt.`topic_id`
                            WHERE t.`topic_bumped` < NOW() - INTERVAL 1 MONTH
                        ",
                    ],
                    [
                        'name' => 'Synchronise forum_id.',
                        'type' => 'sql',
                        'run' => $runLowFreq,
                        'command' => "
                            UPDATE `msz_forum_posts` AS p
                            INNER JOIN `msz_forum_topics` AS t
                            ON t.`topic_id` = p.`topic_id`
                            SET p.`forum_id` = t.`forum_id`
                        ",
                    ],
                    [
                        'name' => 'Recount forum topics and posts.',
                        'type' => 'func',
                        'run' => $runLowFreq,
                        'command' => 'forum_count_synchronise',
                    ],
                    [
                        'name' => 'Clean up expired tfa tokens.',
                        'type' => 'sql',
                        'run' => true,
                        'command' => "
                            DELETE FROM `msz_auth_tfa`
                            WHERE `tfa_created` < NOW() - INTERVAL 15 MINUTE
                        ",
                    ],
                ];

                foreach($cronTasks as $cronTask) {
                    if($cronTask['run']) {
                        echo $cronTask['name'] . PHP_EOL;

                        switch($cronTask['type']) {
                            case 'sql':
                                DB::exec($cronTask['command']);
                                break;

                            case 'func':
                                call_user_func($cronTask['command']);
                                break;
                        }
                    }
                }
                break;

            case 'migrate':
                $doRollback = !empty($argv[2]) && $argv[2] === 'rollback';

                touch(MSZ_ROOT . '/.migrating');

                echo "Creating migration manager.." . PHP_EOL;
                $migrationManager = new DatabaseMigrationManager(DB::getPDO(), MSZ_ROOT . '/database');
                $migrationManager->setLogger(function ($log) {
                    echo $log . PHP_EOL;
                });

                if($doRollback) {
                    $migrationManager->rollback();
                } else {
                    $migrationManager->migrate();
                }

                $errors = $migrationManager->getErrors();
                $errorCount = count($errors);

                if($errorCount < 1) {
                    echo 'Completed with no errors!' . PHP_EOL;
                } else {
                    echo PHP_EOL . "There were {$errorCount} errors during the migrations..." . PHP_EOL;

                    foreach($errors as $error) {
                        echo $error . PHP_EOL;
                    }
                }

                unlink(MSZ_ROOT . '/.migrating');
                break;

            case 'new-mig':
                if(empty($argv[2])) {
                    echo 'Specify a migration name.' . PHP_EOL;
                    return;
                }

                if(!preg_match('#^([a-z_]+)$#', $argv[2])) {
                    echo 'Migration name may only contain alpha and _ characters.' . PHP_EOL;
                    return;
                }

                $filename = date('Y_m_d_His_') . trim($argv[2], '_') . '.php';
                $filepath = MSZ_ROOT . '/database/' . $filename;
                $namespace = snake_to_camel($argv[2]);
                $template = <<<MIG
<?php
namespace Misuzu\DatabaseMigrations\\$namespace;

use PDO;

function migrate_up(PDO \$conn): void {
    \$conn->exec("
        CREATE TABLE ...
    ");
}

function migrate_down(PDO \$conn): void {
    \$conn->exec("
        DROP TABLE ...
    ");
}

MIG;

                file_put_contents($filepath, $template);

                echo "Template for '{$namespace}' has been created." . PHP_EOL;
                break;

            case 'twitter-auth':
                $apiKey = Config::get('twitter.api.key', Config::TYPE_STR);
                $apiSecret = Config::get('twitter.api.secret', Config::TYPE_STR);

                if(empty($apiKey) || empty($apiSecret)) {
                    echo 'No Twitter api keys set in config.' . PHP_EOL;
                    break;
                }

                Twitter::init($apiKey, $apiSecret);
                echo 'Twitter Authentication' . PHP_EOL;

                $authPage = Twitter::createAuth();

                if(empty($authPage)) {
                    echo 'Request to begin authentication failed.' . PHP_EOL;
                    break;
                }

                echo 'Go to the page below and paste the pin code displayed.' . PHP_EOL . $authPage . PHP_EOL;

                $pin = readline('Pin: ');
                $authComplete = Twitter::completeAuth($pin);

                if(empty($authComplete)) {
                    echo 'Invalid pin code.' . PHP_EOL;
                    break;
                }

                echo 'Authentication successful!' . PHP_EOL
                    . "Token: {$authComplete['token']}" . PHP_EOL
                    . "Token Secret: {$authComplete['token_secret']}" . PHP_EOL;
                break;

            default:
                echo 'Unknown command.' . PHP_EOL;
                break;
        }
    }
} else {
    if(!mb_check_encoding()) {
        http_response_code(415);
        echo 'Invalid request encoding.';
        exit;
    }

    ob_start();

    if(!is_readable(MSZ_STORAGE) || !is_writable(MSZ_STORAGE)) {
        echo 'Cannot access storage directory.';
        exit;
    }

    GeoIP::init(Config::get('geoip.database', Config::TYPE_STR, '/var/lib/GeoIP/GeoLite2-Country.mmdb'));

    if(!MSZ_DEBUG) {
        $twigCache = sys_get_temp_dir() . '/msz-tpl-cache-' . md5(MSZ_ROOT);
        mkdirs($twigCache, true);
    }

    Template::init($twigCache ?? null, MSZ_DEBUG);

    Template::set('globals', [
        'site_name' => Config::get('site.name', Config::TYPE_STR, 'Misuzu'),
        'site_description' => Config::get('site.desc', Config::TYPE_STR),
        'site_url' => Config::get('site.url', Config::TYPE_STR),
        'site_twitter' => Config::get('social.twitter', Config::TYPE_STR),
    ]);

    Template::addPath(MSZ_ROOT . '/templates');

    if(file_exists(MSZ_ROOT . '/.migrating')) {
        http_response_code(503);
        Template::render('home.migration');
        exit;
    }

    if(!empty($_COOKIE['msz_uid']) && !empty($_COOKIE['msz_sid'])
        && ctype_digit($_COOKIE['msz_uid']) && ctype_xdigit($_COOKIE['msz_sid'])
        && strlen($_COOKIE['msz_sid']) === 64) {
        $_COOKIE['msz_auth'] = Base64::decode(user_session_cookie_pack($_COOKIE['msz_uid'], $_COOKIE['msz_sid']), true);
        setcookie('msz_auth', $_COOKIE['msz_auth'], strtotime('1 year'), '/', '.' . $_SERVER['HTTP_HOST'], !empty($_SERVER['HTTPS']), true);
        setcookie('msz_uid', '', -3600, '/', '', !empty($_SERVER['HTTPS']), true);
        setcookie('msz_sid', '', -3600, '/', '', !empty($_SERVER['HTTPS']), true);
    }

    if(!empty($_COOKIE['msz_auth']) && is_string($_COOKIE['msz_auth'])) {
        $cookieData = user_session_cookie_unpack(Base64::decode($_COOKIE['msz_auth'], true));

        if(!empty($cookieData) && user_session_start($cookieData['user_id'], $cookieData['session_token'])) {
            $userDisplayInfo = DB::prepare('
                SELECT
                    u.`user_id`, u.`username`, u.`user_background_settings`, u.`user_deleted`,
                    COALESCE(u.`user_colour`, r.`role_colour`) AS `user_colour`
                FROM `msz_users` AS u
                LEFT JOIN `msz_roles` AS r
                ON u.`display_role` = r.`role_id`
                WHERE `user_id` = :user_id
            ');
            $userDisplayInfo->bind('user_id', $cookieData['user_id']);
            $userDisplayInfo = $userDisplayInfo->fetch();

            if($userDisplayInfo) {
                if(!is_null($userDisplayInfo['user_deleted'])) {
                    setcookie('msz_auth', '', -9001, '/', '.' . $_SERVER['HTTP_HOST'], !empty($_SERVER['HTTPS']), true);
                    setcookie('msz_auth', '', -9001, '/', '', !empty($_SERVER['HTTPS']), true);
                    user_session_stop(true);
                    $userDisplayInfo = [];
                } else {
                    user_bump_last_active($cookieData['user_id']);
                    user_session_bump_active(user_session_current('session_id'));

                    if(user_session_current('session_expires_bump')) {
                        setcookie('msz_auth', $_COOKIE['msz_auth'], strtotime('1 month'), '/', '.' . $_SERVER['HTTP_HOST'], !empty($_SERVER['HTTPS']), true);
                    }

                    $userDisplayInfo['perms'] = perms_get_user($userDisplayInfo['user_id']);
                    $userDisplayInfo['ban_expiration'] = user_warning_check_expiration($userDisplayInfo['user_id'], MSZ_WARN_BAN);
                    $userDisplayInfo['silence_expiration'] = $userDisplayInfo['ban_expiration'] > 0 ? 0 : user_warning_check_expiration($userDisplayInfo['user_id'], MSZ_WARN_SILENCE);
                }
            }
        }
    }

    CSRF::setGlobalSecretKey(Config::get('csrf.secret', Config::TYPE_STR, 'soup'));
    CSRF::setGlobalIdentity(empty($userDisplayInfo) ? IPAddress::remote() : $cookieData['session_token']);

    if(Config::get('private.enabled', Config::TYPE_BOOL)) {
        $onLoginPage = $_SERVER['PHP_SELF'] === url('auth-login');
        $onPasswordPage = parse_url($_SERVER['PHP_SELF'], PHP_URL_PATH) === url('auth-forgot');
        $misuzuBypassLockdown = !empty($misuzuBypassLockdown) || $onLoginPage;

        if(!$misuzuBypassLockdown) {
            if(user_session_active()) {
                $privatePermCat = Config::get('private.perm.cat', Config::TYPE_STR);
                $privatePermVal = Config::get('private.perm.val', Config::TYPE_INT);

                if(!empty($privatePermCat) && $privatePermVal > 0) {
                    if(!perms_check_user($privatePermCat, $userDisplayInfo['user_id'], $privatePermVal)) {
                        unset($userDisplayInfo);
                        user_session_stop(); // au revoir
                    }
                }
            } elseif(!$onLoginPage && !($onPasswordPage && Config::get('private.allow_password_reset', Config::TYPE_BOOL, true))) {
                url_redirect('auth-login');
                exit;
            }
        }
    }

    if(!empty($userDisplayInfo)) {
        Template::set('current_user', $userDisplayInfo);
    }

    $inManageMode = starts_with($_SERVER['REQUEST_URI'], '/manage');
    $hasManageAccess = !empty($userDisplayInfo['user_id'])
        && !user_warning_check_restriction($userDisplayInfo['user_id'])
        && perms_check_user(MSZ_PERMS_GENERAL, $userDisplayInfo['user_id'], General::PERM_CAN_MANAGE);
    Template::set('has_manage_access', $hasManageAccess);

    if($inManageMode) {
        if(!$hasManageAccess) {
            echo render_error(403);
            exit;
        }

        Template::set('manage_menu', manage_get_menu($userDisplayInfo['user_id'] ?? 0));
    }
}
