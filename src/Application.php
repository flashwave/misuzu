<?php
namespace Misuzu;

use Carbon\Carbon;
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

    private $mailerInstance = null;

    private $geoipInstance = null;

    /**
     * Constructor, called by ApplicationBase::start() which also passes the arguments through.
     * @param null|string $configFile
     * @param bool        $debug
     */
    public function __construct(?string $configFile = null)
    {
        if (!empty(self::$instance)) {
            throw new UnexpectedValueException('An Application has already been set up.');
        }

        self::$instance = $this;
        $this->config = is_file($configFile) ? parse_ini_file($configFile, true, INI_SCANNER_TYPED) : [];

        if ($this->config === false) {
            throw new UnexpectedValueException('Failed to parse configuration.');
        }
    }

    public function getReportInfo(): array
    {
        return [
            $this->config['Exceptions']['report_url'] ?? null,
            $this->config['Exceptions']['hash_key'] ?? null,
        ];
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

        return fix_path_separator(rtrim($path, '/'));
    }

    /**
     * Gets a data storage path.
     * @return string
     */
    public function getStoragePath(): string
    {
        return create_directory($this->config['Storage']['path'] ?? __DIR__ . '/../store');
    }

    public function canAccessStorage(): bool
    {
        $path = $this->getStoragePath();
        return is_readable($path) && is_writable($path);
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

    public function stopSession(): void
    {
        $this->currentSessionId = 0;
        $this->currentUserId = 0;
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
            'max_size' => intval($this->config['Avatar']['max_filesize'] ?? 1000000),
        ];
    }

    public function getBackgroundProps(): array
    {
        return [
            'max_width' => intval($this->config['Background']['max_width'] ?? 3840),
            'max_height' => intval($this->config['Background']['max_height'] ?? 2160),
            'max_size' => intval($this->config['Background']['max_filesize'] ?? 1000000),
        ];
    }

    public function underLockdown(): bool
    {
        return boolval($this->config['Auth']['lockdown'] ?? false);
    }

    public function disableRegistration(): bool
    {
        return $this->underLockdown()
            || $this->getPrivateInfo()['enabled']
            || boolval($this->config['Auth']['prevent_registration'] ?? false);
    }

    public function getPrivateInfo(): array
    {
        return !empty($this->config['Private']) && boolval($this->config['Private']['enabled'])
            ? $this->config['Private']
            : ['enabled' => false];
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

    public function getSiteInfo(): array
    {
        return [
            'site_name' => $this->config['Site']['name'] ?? 'Flashii',
            'site_description' => $this->config['Site']['description'] ?? '',
            'site_twitter' => $this->config['Site']['twitter'] ?? '',
            'site_url' => $this->config['Site']['url'] ?? '',
        ];
    }

    public function getDefaultAvatar(): string
    {
        return $this->getPath($this->config['Avatar']['default_path'] ?? 'public/images/no-avatar.png');
    }

    public function getCsrfSecretKey(): string
    {
        return $this->config['CSRF']['secret_key'] ?? 'insecure';
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
