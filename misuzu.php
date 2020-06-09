<?php
namespace Misuzu;

use PDO;
use Misuzu\Net\GeoIP;
use Misuzu\Net\IPAddress;
use Misuzu\Users\User;
use Misuzu\Users\UserNotFoundException;
use Misuzu\Users\UserSession;
use Misuzu\Users\UserSessionNotFoundException;

define('MSZ_STARTUP', microtime(true));
define('MSZ_ROOT', __DIR__);
define('MSZ_CLI', PHP_SAPI === 'cli');
define('MSZ_DEBUG', is_file(MSZ_ROOT . '/.debug'));
define('MSZ_PHP_MIN_VER', '7.4.0');
define('MSZ_PUBLIC', MSZ_ROOT . '/public');
define('MSZ_SOURCE', MSZ_ROOT . '/src');
define('MSZ_CONFIG', MSZ_ROOT . '/config');
define('MSZ_TEMPLATES', MSZ_ROOT . '/templates');

if(version_compare(PHP_VERSION, MSZ_PHP_MIN_VER, '<'))
    die("Misuzu requires <i>at least</i> PHP <b>" . MSZ_PHP_MIN_VER . "</b> to run.\r\n");
if(!extension_loaded('curl') || !extension_loaded('intl') || !extension_loaded('json')
    || !extension_loaded('mbstring') || !extension_loaded('pdo') || !extension_loaded('readline')
    || !extension_loaded('xml') || !extension_loaded('zip'))
    die("An extension required by Misuzu hasn't been installed.\r\n");

error_reporting(MSZ_DEBUG ? -1 : 0);
ini_set('display_errors', MSZ_DEBUG ? 'On' : 'Off');

mb_internal_encoding('utf-8');
date_default_timezone_set('utc');
set_include_path(get_include_path() . PATH_SEPARATOR . MSZ_ROOT);

set_exception_handler(function(\Throwable $ex) {
    if(MSZ_CLI) {
        echo (string)$ex;
    } else {
        http_response_code(500);
        ob_clean();

        if(MSZ_DEBUG) {
            header('Content-Type: text/plain; charset=utf-8');
            echo (string)$ex;
        } else {
            header('Content-Type: text/html; charset-utf-8');
            echo file_get_contents(MSZ_TEMPLATES . '/500.html');
        }
    }
    exit;
});

set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline) {
    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    return true;
}, -1);

require_once 'vendor/autoload.php';

spl_autoload_register(function(string $className) {
    $parts = explode('\\', trim($className, '\\'), 2);
    if($parts[0] !== 'Misuzu')
        return;

    $classPath = MSZ_SOURCE . '/' . str_replace('\\', '/', $parts[1]) . '.php';
    if(is_file($classPath))
        require_once $classPath;
});

class_alias(\Misuzu\Http\HttpResponseMessage::class, '\HttpResponse');
class_alias(\Misuzu\Http\HttpRequestMessage::class,  '\HttpRequest');

require_once 'utility.php';
require_once 'src/perms.php';
require_once 'src/manage.php';
require_once 'src/url.php';
require_once 'src/Forum/perms.php';
require_once 'src/Forum/forum.php';
require_once 'src/Forum/leaderboard.php';
require_once 'src/Forum/poll.php';
require_once 'src/Forum/post.php';
require_once 'src/Forum/topic.php';
require_once 'src/Forum/validate.php';

$dbConfig = parse_ini_file(MSZ_CONFIG . '/config.ini', true, INI_SCANNER_TYPED);

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

// replace this with a better storage mechanism
define('MSZ_STORAGE', Config::get('storage.path', Config::TYPE_STR, MSZ_ROOT . '/store'));
if(!is_dir(MSZ_STORAGE))
    mkdir(MSZ_STORAGE, 0775, true);

if(MSZ_CLI) { // Temporary backwards compatibility measure, remove this later
    if(realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
        if(($argv[1] ?? '') === 'cron' && ($argv[2] ?? '') === 'low')
            $argv[2] = '--slow';
        array_shift($argv);
        echo shell_exec(__DIR__ . '/msz ' . implode(' ', $argv));
    }
    return;
}

// Everything below here should eventually be moved to index.php, probably only initialised when required.
// Serving things like the css/js doesn't need to initialise sessions.

if(!mb_check_encoding()) {
    http_response_code(415);
    echo 'Invalid request encoding.';
    exit;
}

ob_start();

if(file_exists(MSZ_ROOT . '/.migrating')) {
    http_response_code(503);
    if(!isset($_GET['_check'])) {
        header('Content-Type: text/html; charset-utf-8');
        echo file_get_contents(MSZ_TEMPLATES . '/503.html');
    }
    exit;
}

if(!is_readable(MSZ_STORAGE) || !is_writable(MSZ_STORAGE)) {
    echo 'Cannot access storage directory.';
    exit;
}

GeoIP::init(Config::get('geoip.database', Config::TYPE_STR, '/var/lib/GeoIP/GeoLite2-Country.mmdb'));

if(!MSZ_DEBUG) {
    $twigCache = sys_get_temp_dir() . '/msz-tpl-cache-' . md5(MSZ_ROOT);
    if(!is_dir($twigCache))
        mkdir($twigCache, 0775, true);
}

Template::init($twigCache ?? null, MSZ_DEBUG);

Template::set('globals', [
    'site_name' => Config::get('site.name', Config::TYPE_STR, 'Misuzu'),
    'site_description' => Config::get('site.desc', Config::TYPE_STR),
    'site_url' => Config::get('site.url', Config::TYPE_STR),
    'site_twitter' => Config::get('social.twitter', Config::TYPE_STR),
]);

Template::addPath(MSZ_TEMPLATES);

if(isset($_COOKIE['msz_uid']) && isset($_COOKIE['msz_sid'])) {
    $authToken = (new AuthToken)
        ->setUserId(filter_input(INPUT_COOKIE, 'msz_uid', FILTER_SANITIZE_NUMBER_INT) ?? 0)
        ->setSessionToken(filter_input(INPUT_COOKIE, 'msz_sid', FILTER_SANITIZE_STRING) ?? '');

    if($authToken->isValid())
        setcookie('msz_auth', $authToken->pack(), strtotime('1 year'), '/', '.' . $_SERVER['HTTP_HOST'], !empty($_SERVER['HTTPS']), true);

    setcookie('msz_uid', '', -3600, '/', '', !empty($_SERVER['HTTPS']), true);
    setcookie('msz_sid', '', -3600, '/', '', !empty($_SERVER['HTTPS']), true);
}

if(!isset($authToken))
    $authToken = AuthToken::unpack(filter_input(INPUT_COOKIE, 'msz_auth', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH) ?? '');

if($authToken->isValid()) {
    try {
        $sessionInfo = $authToken->getSession();
        if($sessionInfo->hasExpired()) {
            $sessionInfo->delete();
        } elseif($sessionInfo->getUserId() === $authToken->getUserId()) {
            $userInfo = $sessionInfo->getUser();
            if(!$userInfo->isDeleted()) {
                $sessionInfo->setCurrent();
                $userInfo->setCurrent();

                $sessionInfo->bump();

                if($sessionInfo->shouldBumpExpire())
                    setcookie('msz_auth', $authToken->pack(), $sessionInfo->getExpiresTime(), '/', '.' . $_SERVER['HTTP_HOST'], !empty($_SERVER['HTTPS']), true);
            }
        }
    } catch(UserNotFoundException $ex) {
        UserSession::unsetCurrent();
        User::unsetCurrent();
    } catch(UserSessionNotFoundException $ex) {
        UserSession::unsetCurrent();
        User::unsetCurrent();
    }

    if(UserSession::hasCurrent()) {
        $userInfo->bumpActivity();

        $userDisplayInfo = DB::prepare('
            SELECT
                u.`user_id`, u.`username`,
                COALESCE(u.`user_colour`, r.`role_colour`) AS `user_colour`
            FROM `msz_users` AS u
            LEFT JOIN `msz_roles` AS r
            ON u.`display_role` = r.`role_id`
            WHERE `user_id` = :user_id
        ')  ->bind('user_id', $userInfo->getId())
            ->fetch();

        $userDisplayInfo['perms'] = perms_get_user($userInfo->getId());
    } else {
        setcookie('msz_auth', '', -9001, '/', '.' . $_SERVER['HTTP_HOST'], !empty($_SERVER['HTTPS']), true);
        setcookie('msz_auth', '', -9001, '/', '', !empty($_SERVER['HTTPS']), true);
    }
}

CSRF::setGlobalSecretKey(Config::get('csrf.secret', Config::TYPE_STR, 'soup'));
CSRF::setGlobalIdentity(UserSession::hasCurrent() ? UserSession::getCurrent()->getToken() : IPAddress::remote());

function mszLockdown(): void {
    global $misuzuBypassLockdown, $userDisplayInfo;

    if(Config::get('private.enabled', Config::TYPE_BOOL)) {
        $onLoginPage = $_SERVER['PHP_SELF'] === url('auth-login');
        $onPasswordPage = parse_url($_SERVER['PHP_SELF'], PHP_URL_PATH) === url('auth-forgot');
        $misuzuBypassLockdown = !empty($misuzuBypassLockdown) || $onLoginPage;

        if(!$misuzuBypassLockdown) {
            if(UserSession::hasCurrent()) {
                $privatePermCat = Config::get('private.perm.cat', Config::TYPE_STR);
                $privatePermVal = Config::get('private.perm.val', Config::TYPE_INT);

                if(!empty($privatePermCat) && $privatePermVal > 0) {
                    if(!perms_check_user($privatePermCat, User::getCurrent()->getId(), $privatePermVal)) {
                        // au revoir
                        unset($userDisplayInfo);
                        UserSession::unsetCurrent();
                        User::unsetCurrent();
                    }
                }
            } elseif(!$onLoginPage && !($onPasswordPage && Config::get('private.allow_password_reset', Config::TYPE_BOOL, true))) {
                url_redirect('auth-login');
                exit;
            }
        }
    }
}

if(parse_url($_SERVER['PHP_SELF'], PHP_URL_PATH) !== '/index.php')
    mszLockdown();

// delete these
if(!empty($userDisplayInfo))
    Template::set('current_user', $userDisplayInfo);
if(!empty($userInfo))
    Template::set('current_user2', $userInfo);

if(Config::has('matomo.endpoint') && Config::has('matomo.javascript') && Config::has('matomo.site')) {
    Template::set([
        'matomo_endpoint' => Config::get('matomo.endpoint', Config::TYPE_STR),
        'matomo_js' => Config::get('matomo.javascript', Config::TYPE_STR),
        'matomo_site' => Config::get('matomo.site', Config::TYPE_STR),
    ]);
}

$inManageMode = starts_with($_SERVER['REQUEST_URI'], '/manage');
$hasManageAccess = User::hasCurrent()
    && !User::getCurrent()->hasActiveWarning()
    && perms_check_user(MSZ_PERMS_GENERAL, User::getCurrent()->getId(), MSZ_PERM_GENERAL_CAN_MANAGE);
Template::set('has_manage_access', $hasManageAccess);

if($inManageMode) {
    if(!$hasManageAccess) {
        echo render_error(403);
        exit;
    }

    Template::set('manage_menu', manage_get_menu(User::getCurrent()->getId()));
}
