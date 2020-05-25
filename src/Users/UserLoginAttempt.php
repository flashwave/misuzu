<?php
namespace Misuzu\Users;

use Misuzu\DB;
use Misuzu\Pagination;
use Misuzu\Net\IPAddress;
use WhichBrowser\Parser as UserAgentParser;

class UserLoginAttempt {
    // Database fields
    private $user_id = null;
    private $attempt_success = false;
    private $attempt_ip = '::1';
    private $attempt_country = 'XX';
    private $attempt_created = null;
    private $attempt_user_agent = '';

    private $user = null;
    private $userLookedUp = false;
    private $uaInfo = null;

    public const TABLE = 'login_attempts';
    private const QUERY_SELECT = 'SELECT %1$s FROM `' . DB::PREFIX . self::TABLE . '` AS '. self::TABLE;
    private const SELECT = '%1$s.`user_id`, %1$s.`attempt_success`, %1$s.`attempt_country`, %1$s.`attempt_user_agent`'
        . ', INET6_NTOA(%1$s.`attempt_ip`) AS `attempt_ip`'
        . ', UNIX_TIMESTAMP(%1$s.`attempt_created`) AS `attempt_created`';

    public function getUserId(): int {
        return $this->user_id < 1 ? -1 : $this->user_id;
    }
    public function getUser(): ?User {
        if(!$this->userLookedUp && ($userId = $this->getUserId()) > 0) {
            $this->userLookedUp = true;
            try {
                $this->user = User::byId($userId);
            } catch(UserNotFoundException $ex) {}
        }
        return $this->user;
    }

    public function isSuccess(): bool {
        return boolval($this->attempt_success);
    }

    public function getRemoteAddress(): string {
        return $this->attempt_ip;
    }

    public function getCountry(): string {
        return $this->attempt_country;
    }
    public function getCountryName(): string {
        return get_country_name($this->getCountry());
    }

    public function getCreatedTime(): int {
        return $this->attempt_created === null ? -1 : $this->attempt_created;
    }

    public function getUserAgent(): string {
        return $this->attempt_user_agent;
    }
    public function getUserAgentInfo(): UserAgentParser {
        if($this->uaInfo === null)
            $this->uaInfo = new UserAgentParser($this->getUserAgent());
        return $this->uaInfo;
    }

    public static function remaining(?string $remoteAddr = null): int {
        $remoteAddr = $ipAddress ?? IPAddress::remote();
        return (int)DB::prepare(
            'SELECT 5 - COUNT(*)'
            . ' FROM `' . DB::PREFIX . self::TABLE . '`'
            . ' WHERE `attempt_success` = 0'
            . ' AND `attempt_created` > NOW() - INTERVAL 1 HOUR'
            . ' AND `attempt_ip` = INET6_ATON(:remote_ip)'
        )   ->bind('remote_ip', $remoteAddr)
            ->fetchColumn();
    }

    public static function create(bool $success, ?User $user = null, ?string $remoteAddr = null, string $userAgent = null): void {
        $remoteAddr = $ipAddress ?? IPAddress::remote();
        $userAgent = $userAgent ?? filter_input(INPUT_SERVER, 'HTTP_USER_AGENT', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH) ?? '';
        $createLog = DB::prepare(
            'INSERT INTO `' . DB::PREFIX . self::TABLE . '` (`user_id`, `attempt_success`, `attempt_ip`, `attempt_country`, `attempt_user_agent`)'
            . ' VALUES (:user, :success, INET6_ATON(:ip), :country, :user_agent)'
        )   ->bind('user', $user === null ? null : $user->getId()) // this null situation should never ever happen but better safe than sorry !
            ->bind('success', $success ? 1 : 0)
            ->bind('ip', $remoteAddr)
            ->bind('country', IPAddress::country($remoteAddr))
            ->bind('user_agent', $userAgent)
            ->execute();
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
    public static function all(?Pagination $pagination = null, ?User $user = null): array {
        $attemptsQuery = self::byQueryBase()
            . ($user === null ? '' : ' WHERE `user_id` = :user')
            . ' ORDER BY `attempt_created` DESC';

        if($pagination !== null)
            $attemptsQuery .= ' LIMIT :range OFFSET :offset';

        $getAttempts = DB::prepare($attemptsQuery);

        if($user !== null)
            $getAttempts->bind('user', $user->getId());

        if($pagination !== null)
            $getAttempts->bind('range', $pagination->getRange())
                ->bind('offset', $pagination->getOffset());

        return $getAttempts->fetchObjects(self::class);
    }
}
