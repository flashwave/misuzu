<?php
namespace Misuzu\Users;

use InvalidArgumentException;
use Misuzu\DB;
use Misuzu\Pagination;
use Misuzu\Net\IPAddress;

class UserWarningException extends UsersException {}
class UserWarningNotFoundException extends UserWarningException {}
class UserWarningCreationFailedException extends UserWarningException {}

class UserWarning {
    // Informational notes on profile, only show up for moderators
    public const TYPE_NOTE = 0;

    // Warning, only shows up to moderators and the user themselves
    public const TYPE_WARN = 1;

    // Silences, prevent a user from speaking and is visible to any logged in user
    public const TYPE_MUTE = 2;

    // Banning, prevents a user from interacting in general
    // User will still be able to log in and change certain details but can no longer partake in community things
    public const TYPE_BAHN = 3;

    private const TYPES = [self::TYPE_NOTE, self::TYPE_WARN, self::TYPE_MUTE, self::TYPE_BAHN];

    private const VISIBLE_TO_STAFF  = self::TYPES;
    private const VISIBLE_TO_USER   = [self::TYPE_WARN, self::TYPE_MUTE, self::TYPE_BAHN];
    private const VISIBLE_TO_PUBLIC = [self::TYPE_MUTE, self::TYPE_BAHN];

    private const HAS_DURATION = [self::TYPE_MUTE, self::TYPE_BAHN];

    private const PROFILE_BACKLOG = 90;

    // Database fields
    private $warning_id = -1;
    private $user_id = -1;
    private $user_ip = '::1';
    private $issuer_id = -1;
    private $issuer_ip = '::1';
    private $warning_created = null;
    private $warning_duration = null;
    private $warning_type = 0;
    private $warning_note = '';
    private $warning_note_private = '';

    private $user = null;
    private $issuer = null;

    public const TABLE = 'user_warnings';
    private const QUERY_SELECT = 'SELECT %1$s FROM `' . DB::PREFIX . self::TABLE . '` AS '. self::TABLE;
    private const SELECT = '%1$s.`warning_id`, %1$s.`user_id`, %1$s.`issuer_id`, %1$s.`warning_type`, %1$s.`warning_note`, %1$s.`warning_note_private`'
        . ', UNIX_TIMESTAMP(%1$s.`warning_created`) AS `warning_created`'
        . ', UNIX_TIMESTAMP(%1$s.`warning_duration`) AS `warning_duration`'
        . ', INET6_NTOA(%1$s.`user_ip`) AS `user_ip`'
        . ', INET6_NTOA(%1$s.`issuer_ip`) AS `issuer_ip`';

    public function getId(): int {
        return $this->warning_id;
    }

    public function getUserId(): int {
        return $this->user_id;
    }
    public function getUser(): User {
        if($this->user === null)
            $this->user = User::byId($this->getUserId());
        return $this->user;
    }

    public function getUserRemoteAddress(): string {
        return $this->user_ip;
    }

    public function getIssuerId(): int {
        return $this->issuer_id;
    }
    public function getIssuer(): User {
        if($this->issuer === null)
            $this->issuer = User::byId($this->getIssuerId());
        return $this->issuer;
    }

    public function getIssuerRemoteAddress(): string {
        return $this->issuer_ip;
    }

    public function getCreatedTime(): int {
        return $this->warning_created === null ? -1 : $this->warning_created;
    }

    public function getExpirationTime(): int {
        return $this->warning_duration === null ? -1 : $this->warning_duration;
    }
    public function hasExpired(): bool {
        return $this->hasDuration() && ($this->getExpirationTime() > 0 && $this->getExpirationTime() < time());
    }

    public function hasDuration(): bool {
        return in_array($this->getType(), self::HAS_DURATION);
    }
    public function getDuration(): int {
        return max(-1, $this->getExpirationTime() - $this->getCreatedTime());
    }

    private const DURATION_DIVS = [
        31536000 => 'year',
         2592000 => 'month',
          604800 => 'week',
           86400 => 'day',
            3600 => 'hour',
              60 => 'minute',
               1 => 'second',
    ];

    public function getDurationString(): string {
        $duration = $this->getDuration();
        if($duration < 1)
            return 'permanent';

        foreach(self::DURATION_DIVS as $span => $name) {
            $display = floor($duration / $span);
            if($display > 0)
                return number_format($display) . ' ' . $name . ($display == 1 ? '' : 's');
        }

        return 'an amount of time';
    }

    public function isPermanent(): bool {
        return $this->hasDuration() && $this->getDuration() < 0;
    }

    public function getType(): int    { return $this->warning_type; }
    public function isNote(): bool    { return $this->getType() === self::TYPE_NOTE; }
    public function isWarning(): bool { return $this->getType() === self::TYPE_WARN; }
    public function isSilence(): bool { return $this->getType() === self::TYPE_MUTE; }
    public function isBan(): bool     { return $this->getType() === self::TYPE_BAHN; }

    public function isVisibleToUser(): bool {
        return in_array($this->getType(), self::VISIBLE_TO_USER);
    }
    public function isVisibleToPublic(): bool {
        return in_array($this->getType(), self::VISIBLE_TO_PUBLIC);
    }

    public function getPublicNote(): string {
        return $this->warning_note;
    }

    public function getPrivateNote(): string {
        return $this->warning_note_private ?? '';
    }
    public function hasPrivateNote(): bool {
        return !empty($this->warning_note_private);
    }

    public function delete(): void {
        DB::prepare('DELETE FROM `' . DB::PREFIX . self::TABLE . '` WHERE `warning_id` = :warning')
            ->bind('warning', $this->warning_id)
            ->execute();
    }

    public static function create(User $user, User $issuer, int $type, int $duration, string $publicNote, ?string $privateNote = null): self {
        if(!in_array($type, self::TYPES))
            throw new InvalidArgumentException('Type was invalid.');

        if(!in_array($type, self::HAS_DURATION))
            $duration = 0;
        else {
            if($duration === 0)
                throw new InvalidArgumentException('Duration must be non-zero.');
            if($duration < 0)
                $duration = -1;
        }

        $warningId = DB::prepare(
            'INSERT INTO `' . DB::PREFIX . self::TABLE . '` (`user_id`, `user_ip`, `issuer_id`, `issuer_ip`, `warning_created`, `warning_duration`, `warning_type`, `warning_note`, `warning_note_private`)'
            . ' VALUES (:user, INET6_ATON(:user_addr), :issuer, INET6_ATON(:issuer_addr), NOW(), IF(:set_duration, NOW() + INTERVAL :duration SECOND, NULL), :type, :public_note, :private_note)'
        )   ->bind('user', $user->getId())
            ->bind('user_addr', $user->getLastRemoteAddress())
            ->bind('issuer', $issuer->getId())
            ->bind('issuer_addr', $issuer->getLastRemoteAddress())
            ->bind('set_duration', $duration > 0 ? 1 : 0)
            ->bind('duration', $duration)
            ->bind('type', $type)
            ->bind('public_note', $publicNote)
            ->bind('private_note', $privateNote)
            ->executeGetId();

        if($warningId < 1)
            throw new UserWarningCreationFailedException;

        return self::byId($warningId);
    }

    private static function countQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf('COUNT(*)', self::TABLE));
    }
    public static function countByRemoteAddress(?string $address = null, bool $withDuration = true): int {
        $address = $address ?? IPAddress::remote();
        return (int)DB::prepare(
            self::countQueryBase()
            . ' WHERE `user_ip` = INET6_ATON(:address)'
            . ' AND `warning_duration` >= NOW()'
            . ($withDuration ? ' AND `warning_type` IN (' . implode(',', self::HAS_DURATION) . ')' : '')
        )->bind('address', $address)->fetchColumn();
    }
    public static function countAll(?User $user = null): int {
        $getCount = DB::prepare(self::countQueryBase() . ($user === null ? '' : ' WHERE `user_id` = :user'));
        if($user !== null)
            $getCount->bind('user', $user->getId());
        return (int)$getCount->fetchColumn();
    }

    private static function byQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf(self::SELECT, self::TABLE));
    }
    public static function byId(int $warningId): self {
        $object = DB::prepare(
            self::byQueryBase() . ' WHERE `warning_id` = :warning'
        )   ->bind('warning', $warningId)
            ->fetchObject(self::class);
        if(!$object)
            throw new UserWarningNotFoundException;
        return $object;
    }
    public static function byUserActive(User $user): ?self {
        return DB::prepare(
            self::byQueryBase()
            . ' WHERE `user_id` = :user'
            . ' AND `warning_type` IN (' . implode(',', self::HAS_DURATION) . ')'
            . ' AND (`warning_duration` IS NULL OR `warning_duration` >= NOW())'
            . ' ORDER BY `warning_type` DESC, `warning_duration` DESC'
        )   ->bind('user', $user->getId())
            ->fetchObject(self::class);
    }
    public static function byProfile(User $user, ?User $viewer = null): array {
        if($viewer === null)
            return [];

        $types = self::VISIBLE_TO_PUBLIC;
        if(perms_check_user(MSZ_PERMS_USER, $viewer->getId(), MSZ_PERM_USER_MANAGE_WARNINGS))
            $types = self::VISIBLE_TO_STAFF;
        elseif($user->getId() === $viewer->getId())
            $types = self::VISIBLE_TO_USER;

        $getObjects = DB::prepare(
            self::byQueryBase()
            . ' WHERE `user_id` = :user'
            . ' AND `warning_type` IN (' . implode(',', $types) . ')'
            . ' AND (`warning_type` = 0 OR `warning_created` >= NOW() - INTERVAL ' . self::PROFILE_BACKLOG . ' DAY OR (`warning_duration` IS NOT NULL AND `warning_duration` >= NOW()))'
            . ' ORDER BY `warning_created` DESC'
        );

        $getObjects->bind('user', $user->getId());

        return $getObjects->fetchObjects(self::class);
    }
    public static function byActive(): array {
        return DB::prepare(
            self::byQueryBase()
            . ' WHERE `warning_type` IN (' . implode(',', self::HAS_DURATION) . ')'
            . ' AND (`warning_duration` IS NULL OR `warning_duration` >= NOW())'
            . ' ORDER BY `warning_type` DESC, `warning_duration` DESC'
        )->fetchObjects(self::class);
    }
    public static function all(?User $user = null, ?Pagination $pagination = null): array {
        $query = self::byQueryBase()
            . ($user === null ? '' : ' WHERE `user_id` = :user')
            . ' ORDER BY `warning_created` DESC';

        if($pagination !== null)
            $query .= ' LIMIT :range OFFSET :offset';

        $getObjects = DB::prepare($query);

        if($user !== null)
            $getObjects->bind('user', $user->getId());

        if($pagination !== null)
            $getObjects->bind('range', $pagination->getRange())
                ->bind('offset', $pagination->getOffset());

        return $getObjects->fetchObjects(self::class);
    }
}
