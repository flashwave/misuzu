<?php
namespace Misuzu;

use Misuzu\DB;
use Misuzu\Pagination;
use Misuzu\Net\IPAddress;
use Misuzu\Users\User;
use Misuzu\Users\UserNotFoundException;

class AuditLog {
    public const PERSONAL_EMAIL_CHANGE        = 'PERSONAL_EMAIL_CHANGE';
    public const PERSONAL_PASSWORD_CHANGE     = 'PERSONAL_PASSWORD_CHANGE';
    public const PERSONAL_SESSION_DESTROY     = 'PERSONAL_SESSION_DESTROY';
    public const PERSONAL_SESSION_DESTROY_ALL = 'PERSONAL_SESSION_DESTROY_ALL';
    public const PERSONAL_DATA_DOWNLOAD       = 'PERSONAL_DATA_DOWNLOAD';

    public const PASSWORD_RESET               = 'PASSWORD_RESET';

    public const CHANGELOG_ENTRY_CREATE       = 'CHANGELOG_ENTRY_CREATE';
    public const CHANGELOG_ENTRY_EDIT         = 'CHANGELOG_ENTRY_EDIT';
    public const CHANGELOG_TAG_ADD            = 'CHANGELOG_TAG_ADD';
    public const CHANGELOG_TAG_REMOVE         = 'CHANGELOG_TAG_REMOVE';
    public const CHANGELOG_TAG_CREATE         = 'CHANGELOG_TAG_CREATE';
    public const CHANGELOG_TAG_EDIT           = 'CHANGELOG_TAG_EDIT';
    public const CHANGELOG_ACTION_CREATE      = 'CHANGELOG_ACTION_CREATE';
    public const CHANGELOG_ACTION_EDIT        = 'CHANGELOG_ACTION_EDIT';

    public const COMMENT_ENTRY_DELETE         = 'COMMENT_ENTRY_DELETE';
    public const COMMENT_ENTRY_DELETE_MOD     = 'COMMENT_ENTRY_DELETE_MOD';
    public const COMMENT_ENTRY_RESTORE        = 'COMMENT_ENTRY_RESTORE';

    public const NEWS_POST_CREATE             = 'NEWS_POST_CREATE';
    public const NEWS_POST_EDIT               = 'NEWS_POST_EDIT';
    public const NEWS_CATEGORY_CREATE         = 'NEWS_CATEGORY_CREATE';
    public const NEWS_CATEGORY_EDIT           = 'NEWS_CATEGORY_EDIT';

    public const FORUM_TOPIC_DELETE           = 'FORUM_TOPIC_DELETE';
    public const FORUM_TOPIC_RESTORE          = 'FORUM_TOPIC_RESTORE';
    public const FORUM_TOPIC_NUKE             = 'FORUM_TOPIC_NUKE';
    public const FORUM_TOPIC_BUMP             = 'FORUM_TOPIC_BUMP';
    public const FORUM_TOPIC_LOCK             = 'FORUM_TOPIC_LOCK';
    public const FORUM_TOPIC_UNLOCK           = 'FORUM_TOPIC_UNLOCK';

    public const FORUM_POST_EDIT              = 'FORUM_POST_EDIT';
    public const FORUM_POST_DELETE            = 'FORUM_POST_DELETE';
    public const FORUM_POST_RESTORE           = 'FORUM_POST_RESTORE';
    public const FORUM_POST_NUKE              = 'FORUM_POST_NUKE';

    public const FORMATS = [
        self::PERSONAL_EMAIL_CHANGE           => 'Changed e-mail address to %s.',
        self::PERSONAL_PASSWORD_CHANGE        => 'Changed account password.',
        self::PERSONAL_SESSION_DESTROY        => 'Ended session #%d.',
        self::PERSONAL_SESSION_DESTROY_ALL    => 'Ended all personal sessions.',
        self::PERSONAL_DATA_DOWNLOAD          => 'Downloaded archive of account data.',

        self::PASSWORD_RESET                  => 'Successfully used the password reset form to change password.',

        self::CHANGELOG_ENTRY_CREATE          => 'Created a new changelog entry #%d.',
        self::CHANGELOG_ENTRY_EDIT            => 'Edited changelog entry #%d.',
        self::CHANGELOG_TAG_ADD               => 'Added tag #%2$d to changelog entry #%1$d.',
        self::CHANGELOG_TAG_REMOVE            => 'Removed tag #%2$d from changelog entry #%1$d.',
        self::CHANGELOG_TAG_CREATE            => 'Created new changelog tag #%d.',
        self::CHANGELOG_TAG_EDIT              => 'Edited changelog tag #%d.',
        self::CHANGELOG_ACTION_CREATE         => 'Created new changelog action #%d.',
        self::CHANGELOG_ACTION_EDIT           => 'Edited changelog action #%d.',

        self::COMMENT_ENTRY_DELETE            => 'Deleted comment #%d.',
        self::COMMENT_ENTRY_DELETE_MOD        => 'Deleted comment #%d by user #%d %s.',
        self::COMMENT_ENTRY_RESTORE           => 'Restored comment #%d by user #%d %s.',

        self::NEWS_POST_CREATE                => 'Created news post #%d.',
        self::NEWS_POST_EDIT                  => 'Edited news post #%d.',
        self::NEWS_CATEGORY_CREATE            => 'Created news category #%d.',
        self::NEWS_CATEGORY_EDIT              => 'Edited news category #%d.',

        self::FORUM_POST_EDIT                 => 'Edited forum post #%d.',
        self::FORUM_POST_DELETE               => 'Deleted forum post #%d.',
        self::FORUM_POST_RESTORE              => 'Restored forum post #%d.',
        self::FORUM_POST_NUKE                 => 'Nuked forum post #%d.',

        self::FORUM_TOPIC_DELETE              => 'Deleted forum topic #%d.',
        self::FORUM_TOPIC_RESTORE             => 'Restored forum topic #%d.',
        self::FORUM_TOPIC_NUKE                => 'Nuked forum topic #%d.',
        self::FORUM_TOPIC_BUMP                => 'Manually bumped forum topic #%d.',
        self::FORUM_TOPIC_LOCK                => 'Locked forum topic #%d.',
        self::FORUM_TOPIC_UNLOCK              => 'Unlocked forum topic #%d.',
    ];

    // Database fields
    private $user_id = null;
    private $log_action = '';
    private $log_params = [];
    private $log_created = null;
    private $log_ip = '::1';
    private $log_country = 'XX';

    private $user = null;
    private $userLookedUp = false;

    public const TABLE = 'audit_log';
    private const QUERY_SELECT = 'SELECT %1$s FROM `' . DB::PREFIX . self::TABLE . '` AS '. self::TABLE;
    private const SELECT = '%1$s.`user_id`, %1$s.`log_action`, %1$s.`log_params`, %1$s.`log_country`'
        . ', INET6_NTOA(%1$s.`log_ip`) AS `log_ip`'
        . ', UNIX_TIMESTAMP(%1$s.`log_created`) AS `log_created`';

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

    public function getAction(): string {
        return $this->log_action;
    }

    public function getParams(): array {
        if(is_string($this->log_params))
            $this->log_params = json_decode($this->log_params) ?? [];
        return $this->log_params;
    }

    public function getCreatedTime(): int {
        return $this->log_created === null ? -1 : $this->log_created;
    }

    public function getRemoteAddress(): string {
        return $this->log_ip;
    }

    public function getCountry(): string {
        return $this->log_country;
    }
    public function getCountryName(): string {
        return get_country_name($this->getCountry());
    }

    public function getString(): string {
        if(!array_key_exists($this->getAction(), self::FORMATS))
            return sprintf('%s(%s)', $this->getAction(), json_encode($this->getParams()));
        return vsprintf(self::FORMATS[$this->getAction()], $this->getParams());
    }

    public static function create(string $action, array $params = [], ?User $user = null, ?string $remoteAddr = null): void {
        $user = $user ?? User::getCurrent();
        $remoteAddr = $ipAddress ?? IPAddress::remote();
        $createLog = DB::prepare(
            'INSERT INTO `' . DB::PREFIX . self::TABLE . '` (`log_action`, `user_id`, `log_params`, `log_ip`, `log_country`)'
            . ' VALUES (:action, :user, :params, INET6_ATON(:ip), :country)'
        )   ->bind('action', $action)
            ->bind('user', $user === null ? null : $user->getId()) // this null situation should never ever happen but better safe than sorry !
            ->bind('params', json_encode($params))
            ->bind('ip', $remoteAddr)
            ->bind('country', IPAddress::country($remoteAddr))
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
        $logsQuery = self::byQueryBase()
            . ($user === null ? '' : ' WHERE `user_id` = :user')
            . ' ORDER BY `log_created` DESC';

        if($pagination !== null)
            $logsQuery .= ' LIMIT :range OFFSET :offset';

        $getLogs = DB::prepare($logsQuery);

        if($user !== null)
            $getLogs->bind('user', $user->getId());

        if($pagination !== null)
            $getLogs->bind('range', $pagination->getRange())
                ->bind('offset', $pagination->getOffset());

        return $getLogs->fetchObjects(self::class);
    }
}
