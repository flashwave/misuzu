<?php
namespace Misuzu\Users;

use Misuzu\DB;
use Misuzu\Pagination;
use Misuzu\Net\IPAddress;
use WhichBrowser\Parser as UserAgentParser;

class UserSessionException extends UsersException {}
class UserSessionCreationFailedException extends UserSessionException {}
class UserSessionNotFoundException extends UserSessionException {}

class UserSession {
    public const TOKEN_SIZE = 64;
    public const LIFETIME = 60 * 60 * 24 * 31;

    // Database fields
    private $session_id = -1;
    private $user_id = -1;
    private $session_key = '';
    private $session_ip = '::1';
    private $session_ip_last = null;
    private $session_user_agent = '';
    private $session_country = 'XX';
    private $session_expires = null;
    private $session_expires_bump = 1;
    private $session_created = null;
    private $session_active = null;

    private $user = null;
    private $uaInfo = null;

    private static $localSession = null;

    public const TABLE = 'sessions';
    private const QUERY_SELECT = 'SELECT %1$s FROM `' . DB::PREFIX . self::TABLE . '` AS '. self::TABLE;
    private const SELECT = '%1$s.`session_id`, %1$s.`user_id`, %1$s.`session_key`, %1$s.`session_user_agent`, %1$s.`session_country`, %1$s.`session_expires_bump`'
        . ', INET6_NTOA(%1$s.`session_ip`) AS `session_ip`'
        . ', INET6_NTOA(%1$s.`session_ip_last`) AS `session_ip_last`'
        . ', UNIX_TIMESTAMP(%1$s.`session_created`) AS `session_created`'
        . ', UNIX_TIMESTAMP(%1$s.`session_active`) AS `session_active`'
        . ', UNIX_TIMESTAMP(%1$s.`session_expires`) AS `session_expires`';

    public function getId(): int {
        return $this->session_id < 1 ? -1 : $this->session_id;
    }

    public function getUserId(): int {
        return $this->user_id < 1 ? -1 : $this->user_id;
    }
    public function getUser(): User {
        if($this->user === null)
            $this->user = User::byId($this->getUserId());
        return $this->user;
    }

    public function getToken(): string {
        return $this->session_key;
    }

    public function getInitialRemoteAddress(): string {
        return $this->session_ip;
    }

    public function getLastRemoteAddress(): string {
        return $this->session_ip_last ?? '';
    }
    public function hasLastRemoteAddress(): bool {
        return !empty($this->session_ip_last);
    }
    public function setLastRemoteAddress(string $remoteAddr): self {
        $this->session_ip_last = $remoteAddr;
        return $this;
    }

    public function getUserAgent(): string {
        return $this->session_user_agent;
    }
    public function getUserAgentInfo(): UserAgentParser {
        if($this->uaInfo === null)
            $this->uaInfo = new UserAgentParser($this->getUserAgent());
        return $this->uaInfo;
    }

    public function getCountry(): string {
        return $this->session_country;
    }
    public function getCountryName(): string {
        return get_country_name($this->getCountry());
    }

    public function getCreatedTime(): int {
        return $this->session_created === null ? -1 : $this->session_created;
    }

    public function getActiveTime(): int {
        return $this->session_active === null ? -1 : $this->session_active;
    }
    public function hasActiveTime(): bool {
        return $this->session_active !== null;
    }
    public function setActiveTime(int $timestamp): self {
        if($timestamp > $this->session_active)
            $this->session_active = $timestamp;
        return $this;
    }

    public function getExpiresTime(): int {
        return $this->session_expires === null ? -1 : $this->session_expires;
    }
    public function setExpiresTime(int $timestamp): self {
        $this->session_expires = $timestamp;
        return $this;
    }
    public function hasExpired(): bool {
        return $this->getExpiresTime() <= time();
    }

    public function shouldBumpExpire(): bool {
        return boolval($this->session_expires_bump);
    }

    public function bump(bool $callUpdate = true, ?int $timestamp = null, ?string $remoteAddr = null): void {
        $timestamp = $timestamp ?? time();
        $remoteAddr = $remoteAddr ?? IPAddress::remote();

        $this->setActiveTime($timestamp)
            ->setLastRemoteAddress($remoteAddr);

        if($this->shouldBumpExpire())
            $this->setExpiresTime($timestamp + self::LIFETIME);

        if($callUpdate)
            $this->update();
    }

    public function delete(): void {
        DB::prepare('DELETE FROM `' . DB::PREFIX . self::TABLE . '` WHERE `session_id` = :session')
            ->bind('session', $this->getId())
            ->execute();
    }

    public static function purgeUser(User $user): void {
        DB::prepare('DELETE FROM `' . DB::PREFIX . self::TABLE . '` WHERE `user_id` = :user')
            ->bind('user', $user->getId())
            ->execute();
    }

    public function setCurrent(): void {
        self::$localSession = $this;
    }
    public static function unsetCurrent(): void {
        self::$localSession = null;
    }
    public static function getCurrent(): ?self {
        return self::$localSession;
    }
    public static function hasCurrent(): bool {
        return self::$localSession !== null;
    }

    public static function generateToken(): string {
        return bin2hex(random_bytes(self::TOKEN_SIZE / 2));
    }

    public function update(): void {
        DB::prepare(
            'UPDATE `' . DB::PREFIX . self::TABLE . '`'
            . ' SET `session_active` = FROM_UNIXTIME(:active), `session_ip_last` = INET6_ATON(:remote_addr), `session_expires` = FROM_UNIXTIME(:expires)'
            . ' WHERE `session_id` = :session'
        )   ->bind('active', $this->session_active)
            ->bind('remote_addr', $this->session_ip_last)
            ->bind('expires', $this->session_expires)
            ->bind('session', $this->session_id)
            ->execute();
    }

    public static function create(User $user, ?string $remoteAddr = null, ?string $userAgent = null, ?string $token = null): self {
        $remoteAddr = $remoteAddr ?? IPAddress::remote();
        $userAgent = $userAgent ?? filter_input(INPUT_SERVER, 'HTTP_USER_AGENT', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH) ?? '';
        $token = $token ?? self::generateToken();

        $sessionId = DB::prepare(
            'INSERT INTO `' . DB::PREFIX . self::TABLE . '`'
            . ' (`user_id`, `session_ip`, `session_country`, `session_user_agent`, `session_key`, `session_created`, `session_expires`)'
            . ' VALUES (:user, INET6_ATON(:remote_addr), :country, :user_agent, :token, NOW(), NOW() + INTERVAL :expires SECOND)'
        )   ->bind('user', $user->getId())
            ->bind('remote_addr', $remoteAddr)
            ->bind('country', IPAddress::country($remoteAddr))
            ->bind('user_agent', $userAgent)
            ->bind('token', $token)
            ->bind('expires', self::LIFETIME)
            ->executeGetId();

        if($sessionId < 1)
            throw new UserSessionCreationFailedException;

        return self::byId($sessionId);
    }

    private static function countQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf('COUNT(*)', self::TABLE));
    }
    public static function countAll(?User $user = null): int {
        $getCount = DB::prepare(
            self::countQueryBase()
            . ($user === null ? '' : ' WHERE `user_id` = :user')
        );
        if($user !== null)
            $getCount->bind('user', $user->getId());
        return (int)$getCount->fetchColumn();
    }

    private static function byQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf(self::SELECT, self::TABLE));
    }
    public static function byId(int $sessionId): self {
        $session = DB::prepare(self::byQueryBase() . ' WHERE `session_id` = :session_id')
            ->bind('session_id', $sessionId)
            ->fetchObject(self::class);

        if(!$session)
            throw new UserSessionNotFoundException;

        return $session;
    }
    public static function byToken(string $token): self {
        $session = DB::prepare(self::byQueryBase() . ' WHERE `session_key` = :token')
            ->bind('token', $token)
            ->fetchObject(self::class);

        if(!$session)
            throw new UserSessionNotFoundException;

        return $session;
    }
    public static function all(?Pagination $pagination = null, ?User $user = null): array {
        $sessionsQuery = self::byQueryBase()
            . ($user === null ? '' : ' WHERE `user_id` = :user')
            . ' ORDER BY `session_created` DESC';

        if($pagination !== null)
            $sessionsQuery .= ' LIMIT :range OFFSET :offset';

        $getSessions = DB::prepare($sessionsQuery);

        if($user !== null)
            $getSessions->bind('user', $user->getId());

        if($pagination !== null)
            $getSessions->bind('range', $pagination->getRange())
                ->bind('offset', $pagination->getOffset());

        return $getSessions->fetchObjects(self::class);
    }
}
