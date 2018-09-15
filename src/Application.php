<?php
namespace Misuzu;

use Carbon\Carbon;
use Misuzu\IO\Directory;
use Misuzu\IO\DirectoryDoesNotExistException;
use Misuzu\Users\Session;
use UnexpectedValueException;
use InvalidArgumentException;
use Swift_Mailer;
use Swift_NullTransport;
use Swift_SmtpTransport;
use Swift_SendmailTransport;
use GeoIp2\Database\Reader as GeoIP;

/**
 * Handles the set up procedures.
 * @package Misuzu
 */
final class Application
{
    private static $instance = null;

    /**
     * Whether the application is in debug mode, this should only be set in the constructor and never altered.
     * @var bool
     */
    private $debugMode = false;

    /**
     * Array of database connection names, first in the list is assumed to be the default.
     */
    private const DATABASE_CONNECTIONS = [
        'mysql-main',
    ];

    private const MAIL_TRANSPORT = [
        'null' => Swift_NullTransport::class,
        'smtp' => Swift_SmtpTransport::class,
        'sendmail' => Swift_SendmailTransport::class,
    ];

    /**
     * Active Session ID.
     * @var int
     */
    private $currentSessionId = 0;

    /**
     * Active User ID.
     * @var int
     */
    private $currentUserId = 0;

    private $config = [];

    /**
     * TemplatingEngine instance.
     * @var \Misuzu\TemplateEngine
     */
    private $templatingInstance = null;

    private $mailerInstance = null;

    private $geoipInstance = null;

    private $startupTime = 0;

    /**
     * Constructor, called by ApplicationBase::start() which also passes the arguments through.
     * @param null|string $configFile
     * @param bool        $debug
     */
    public function __construct(?string $configFile = null, bool $debug = false)
    {
        if (!empty(self::$instance)) {
            throw new UnexpectedValueException('An Application has already been set up.');
        }

        self::$instance = $this;
        $this->startupTime = microtime(true);
        $this->debugMode = $debug;
        $this->config = parse_ini_file($configFile, true, INI_SCANNER_TYPED);

        if ($this->config === false) {
            throw new UnexpectedValueException('Failed to parse configuration.');
        }

        // only use this error handler in prod mode, dev uses Whoops now
        if (!$debug) {
            ExceptionHandler::register(
                false,
                $this->config['Exceptions']['report_url'] ?? null,
                $this->config['Exceptions']['hash_key'] ?? null
            );
        }
    }

    public function getTimeSinceStart(): float
    {
        return microtime(true) - $this->startupTime;
    }

    /**
     * Gets whether we're in debug mode or not.
     * @return bool
     */
    public function inDebugMode(): bool
    {
        return $this->debugMode;
    }

    /**
     * Gets a storage path.
     * @param string $path
     * @return string
     */
    public function getPath(string $path): string
    {
        if (!starts_with($path, '/') && mb_substr($path, 1, 2) !== ':\\') {
            $path = __DIR__ . '/../' . $path;
        }

        return Directory::fixSlashes(rtrim($path, '/'));
    }

    /**
     * Gets a data storage path, with config storage path prefix.
     * @param string $append
     * @return Directory
     * @throws DirectoryDoesNotExistException
     * @throws IO\DirectoryExistsException
     */
    public function getStoragePath(string $append = ''): Directory
    {
        if (starts_with($append, '/')) {
            $path = $append;
        } else {
            $path = $this->config['Storage']['path'] ?? __DIR__ . '/../store';

            if (!empty($append)) {
                $path .= '/' . $append;
            }
        }

        return Directory::createOrOpen($this->getPath($path));
    }

    /**
     * Gets a data store, with config overrides!
     * @param string $purpose
     * @return Directory
     * @throws DirectoryDoesNotExistException
     * @throws IO\DirectoryExistsException
     */
    public function getStore(string $purpose): Directory
    {
        $override_key = 'override_' . str_replace('/', '_', $purpose);

        if (array_key_exists('Storage', $this->config)) {
            try {
                return new Directory($this->config['Storage'][$override_key] ?? '');
            } catch (DirectoryDoesNotExistException $ex) {
                // fall through and just get the default path.
            }
        }

        return $this->getStoragePath($purpose);
    }

    /**
     * Starts a user session.
     * @param int $userId
     * @param string $sessionKey
     */
    public function startSession(int $userId, string $sessionKey): void
    {
        $dbc = Database::connection();

        $findSession = $dbc->prepare('
            SELECT `session_id`, `expires_on`
            FROM `msz_sessions`
            WHERE `user_id` = :user_id
            AND `session_key` = :session_key
        ');
        $findSession->bindValue('user_id', $userId);
        $findSession->bindValue('session_key', $sessionKey);
        $sessionData = $findSession->execute() ? $findSession->fetch() : false;

        if ($sessionData) {
            $expiresOn = new Carbon($sessionData['expires_on']);

            if ($expiresOn->isPast()) {
                $deleteSession = $dbc->prepare('
                    DELETE FROM `msz_sessions`
                    WHERE `session_id` = :session_id
                ');
                $deleteSession->bindValue('session_id', $sessionData['session_id']);
                $deleteSession->execute();
            } else {
                $this->currentSessionId = (int)$sessionData['session_id'];
                $this->currentUserId = $userId;
            }
        }
    }

    public function hasActiveSession(): bool
    {
        return $this->getSessionId() > 0;
    }

    public function getSessionId(): int
    {
        return $this->currentSessionId;
    }

    public function getUserId(): int
    {
        return $this->currentUserId;
    }

    /**
     * Sets up the database module.
     */
    public function startDatabase(): void
    {
        if (Database::hasInstance()) {
            throw new UnexpectedValueException('Database has already been started.');
        }

        $connections = [];

        foreach (self::DATABASE_CONNECTIONS as $name) {
            $connections[$name] = $this->config["Database.{$name}"] ?? [];
        }

        new Database($connections, self::DATABASE_CONNECTIONS[0]);
    }

    /**
     * Sets up the caching stuff.
     */
    public function startCache(): void
    {
        if (Cache::hasInstance()) {
            throw new UnexpectedValueException('Cache has already been started.');
        }

        new Cache(
            $this->config['Cache']['host'] ?? null,
            $this->config['Cache']['port'] ?? null,
            $this->config['Cache']['database'] ?? null,
            $this->config['Cache']['password'] ?? null,
            $this->config['Cache']['prefix'] ?? ''
        );
    }

    /**
     * Sets up the templating engine module.
     */
    public function startTemplating(): void
    {
        if (!is_null($this->templatingInstance)) {
            throw new UnexpectedValueException('Templating module has already been started.');
        }

        tpl_init([
            'debug' => $this->debugMode,
        ]);

        tpl_var('globals', [
            'site_name' => $this->config['Site']['name'] ?? 'Flashii',
            'site_description' => $this->config['Site']['description'] ?? '',
            'site_twitter' => $this->config['Site']['twitter'] ?? '',
            'site_url' => $this->config['Site']['url'] ?? '',
        ]);

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

        tpl_add_function('git_commit_hash');
        tpl_add_function('git_branch');
        tpl_add_function('csrf_token', false, 'tmp_csrf_token');

        tpl_var('app', $this);
    }

    public function startMailer(): void
    {
        if (!empty($this->mailerInstance)) {
            return;
        }

        if (array_key_exists('Mail', $this->config) && array_key_exists('method', $this->config['Mail'])) {
            $method = mb_strtolower($this->config['Mail']['method'] ?? '');
        }

        if (empty($method) || !array_key_exists($method, self::MAIL_TRANSPORT)) {
            $method = 'null';
        }

        $class = self::MAIL_TRANSPORT[$method];
        $transport = new $class;

        switch ($method) {
            case 'sendmail':
                if (array_key_exists('command', $this->config['Mail'])) {
                    $transport->setCommand($this->config['Mail']['command']);
                }
                break;

            case 'smtp':
                $transport->setHost($this->config['Mail']['host'] ?? '');
                $transport->setPort(intval($this->config['Mail']['port'] ?? 25));

                if (array_key_exists('encryption', $this->config['Mail'])) {
                    $transport->setEncryption($this->config['Mail']['encryption']);
                }

                if (array_key_exists('username', $this->config['Mail'])) {
                    $transport->setUsername($this->config['Mail']['username']);
                }

                if (array_key_exists('password', $this->config['Mail'])) {
                    $transport->setPassword($this->config['Mail']['password']);
                }
                break;
        }

        $this->mailerInstance = new Swift_Mailer($transport);
    }

    public function getMailer(): Swift_Mailer
    {
        if (empty($this->mailerInstance)) {
            $this->startMailer();
        }

        return $this->mailerInstance;
    }

    public static function mailer(): Swift_Mailer
    {
        return self::getInstance()->getMailer();
    }

    public function getMailSender(): array
    {
        return [
            ($this->config['Mail']['sender_email'] ?? 'sys@msz.lh') => ($this->config['Mail']['sender_name'] ?? 'Misuzu System')
        ];
    }

    public function startGeoIP(): void
    {
        if (!empty($this->geoipInstance)) {
            return;
        }

        $this->geoipInstance = new GeoIP($this->config['GeoIP']['database_path'] ?? '');
    }

    public function getGeoIP(): GeoIP
    {
        if (empty($this->geoipInstance)) {
            $this->startGeoIP();
        }

        return $this->geoipInstance;
    }

    public static function geoip(): GeoIP
    {
        return self::getInstance()->getGeoIP();
    }

    public function getAvatarProps(): array
    {
        return [
            'max_width' => intval($this->config['Avatar']['max_width'] ?? 4000),
            'max_height' => intval($this->config['Avatar']['max_height'] ?? 4000),
            'max_filesize' => intval($this->config['Avatar']['max_filesize'] ?? 1000000),
        ];
    }

    public function underLockdown(): bool
    {
        return boolval($this->config['Auth']['lockdown'] ?? false);
    }

    public function disableRegistration(): bool
    {
        return $this->underLockdown() || boolval($this->config['Auth']['prevent_registration'] ?? false);
    }

    public function getLinkedData(): array
    {
        if (!($this->config['Site']['embed_linked_data'] ?? false)) {
            return ['embed_linked_data' => false];
        }

        return [
            'embed_linked_data' => true,
            'embed_name' => $this->config['Site']['name'] ?? 'Flashii',
            'embed_url' => $this->config['Site']['url'] ?? '',
            'embed_logo' => $this->config['Site']['external_logo'] ?? '',
            'embed_same_as' => explode(',', $this->config['Site']['social_media'] ?? '')
        ];
    }

    public function getDefaultAvatar(): string
    {
        return $this->getPath($this->config['Avatar']['default_path'] ?? 'public/images/no-avatar.png');
    }

    /**
     * Gets the currently active instance of Application
     * @return Application
     */
    public static function getInstance(): Application
    {
        if (empty(self::$instance)) {
            throw new UnexpectedValueException('No instances.');
        }

        return self::$instance;
    }
}
