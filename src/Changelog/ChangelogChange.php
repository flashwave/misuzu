<?php
namespace Misuzu\Changelog;

use JsonSerializable;
use UnexpectedValueException;
use Misuzu\DB;
use Misuzu\Memoizer;
use Misuzu\Pagination;
use Misuzu\Comments\CommentsCategory;
use Misuzu\Comments\CommentsCategoryNotFoundException;
use Misuzu\Parsers\Parser;
use Misuzu\Users\User;
use Misuzu\Users\UserNotFoundException;

class ChangelogChangeException extends ChangelogException {}
class ChangelogChangeNotFoundException extends ChangelogChangeException {}

class ChangelogChange implements JsonSerializable {
    public const ACTION_UNKNOWN = 1;
    public const ACTION_ADD     = 1;
    public const ACTION_REMOVE  = 2;
    public const ACTION_UPDATE  = 3;
    public const ACTION_FIX     = 4;
    public const ACTION_IMPORT  = 5;
    public const ACTION_REVERT  = 6;

    private const ACTION_STRINGS = [
        self::ACTION_UNKNOWN => ['unknown', 'Changed'],
        self::ACTION_ADD     => ['add',     'Added'],
        self::ACTION_REMOVE  => ['remove',  'Removed'],
        self::ACTION_UPDATE  => ['update',  'Updated'],
        self::ACTION_FIX     => ['fix',     'Fixed'],
        self::ACTION_IMPORT  => ['import',  'Imported'],
        self::ACTION_REVERT  => ['revert',  'Reverted'],
    ];

    public const DEFAULT_DATE = '0000-00-00';

    // Database fields
    private $change_id = -1;
    private $user_id = null;
    private $change_action = null; // defaults null apparently, probably a previous oversight
    private $change_created = null;
    private $change_log = '';
    private $change_text = '';

    private $user = null;
    private $userLookedUp = false;
    private $comments = null;
    private $tags = null;
    private $tagRelations = null;

    public const TABLE = 'changelog_changes';
    private const QUERY_SELECT = 'SELECT %1$s FROM `' . DB::PREFIX . self::TABLE . '` AS '. self::TABLE;
    private const SELECT = '%1$s.`change_id`, %1$s.`user_id`, %1$s.`change_action`, %1$s.`change_log`, %1$s.`change_text`'
        . ', UNIX_TIMESTAMP(%1$s.`change_created`) AS `change_created`';

    public function getId(): int {
        return $this->change_id < 1 ? -1 : $this->change_id;
    }

    public function getUserId(): int {
        return $this->user_id < 1 ? -1 : $this->user_id;
    }
    public function setUserId(?int $userId): self {
        $this->user_id = $userId;
        $this->userLookedUp = false;
        $this->user = null;
        return $this;
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
    public function setUser(?User $user): self {
        $this->user_id = $user === null ? null : $user->getId();
        $this->userLookedUp = true;
        $this->user = $user;
        return $this;
    }

    public function getAction(): int {
        return $this->change_action ?? self::ACTION_UNKNOWN;
    }
    public function setAction(int $actionId): self {
        $this->change_action = $actionId;
        return $this;
    }
    private function getActionInfo(): array {
        return self::ACTION_STRINGS[$this->getAction()] ?? self::ACTION_STRINGS[self::ACTION_UNKNOWN];
    }
    public function getActionClass(): string {
        return $this->getActionInfo()[0];
    }
    public function getActionString(): string {
        return $this->getActionInfo()[1];
    }

    public function getCreatedTime(): int {
        return $this->change_created ?? -1;
    }
    public function getDate(): string {
        return ($time = $this->getCreatedTime()) < 0 ? self::DEFAULT_DATE : gmdate('Y-m-d', $time);
    }

    public function getHeader(): string {
        return $this->change_log;
    }
    public function setHeader(string $header): self {
        $this->change_log = $header;
        return $this;
    }

    public function getBody(): string {
        return $this->change_text ?? '';
    }
    public function setBody(string $body): self {
        $this->change_text = $body;
        return $this;
    }
    public function hasBody(): bool {
        return !empty($this->change_text);
    }
    public function getParsedBody(): string {
        return Parser::instance(Parser::MARKDOWN)->parseText($this->getBody());
    }

    public function getCommentsCategoryName(): ?string {
        return ($date = $this->getDate()) === self::DEFAULT_DATE ? null : sprintf('changelog-date-%s', $this->getDate());
    }
    public function hasCommentsCategory(): bool {
        return $this->getCreatedTime() >= 0;
    }
    public function getCommentsCategory(): CommentsCategory {
        if($this->comments === null) {
            $categoryName = $this->getCommentsCategoryName();

            if(empty($categoryName))
                throw new UnexpectedValueException('Change comments category name is empty.');

            try {
                $this->comments = CommentsCategory::byName($categoryName);
            } catch(CommentsCategoryNotFoundException $ex) {
                $this->comments = new CommentsCategory($categoryName);
                $this->comments->save();
            }
        }
        return $this->comments;
    }

    public function getTags(): array {
        if($this->tags === null)
            $this->tags = ChangelogTag::byChange($this);
        return $this->tags;
    }
    public function getTagRelations(): array {
        if($this->tagRelations === null)
            $this->tagRelations = ChangelogChangeTag::byChange($this);
        return $this->tagRelations;
    }
    public function setTags(array $tags): self {
        ChangelogChangeTag::purgeChange($this);
        foreach($tags as $tag)
            if($tag instanceof ChangelogTag)
                ChangelogChangeTag::create($this, $tag);
        $this->tags = $tags;
        $this->tagRelations = null;
        return $this;
    }
    public function hasTag(ChangelogTag $other): bool {
        foreach($this->getTags() as $tag)
            if($tag->compare($other))
                return true;
        return false;
    }

    public function jsonSerialize() {
        return [
            'id'       => $this->getId(),
            'user'     => $this->getUserId(),
            'action'   => $this->getActionId(),
            'header'   => $this->getHeader(),
            'body'     => $this->getBody(),
            'comments' => $this->getCommentsCategoryName(),
            'created'  => ($time = $this->getCreatedTime()) < 0 ? null : date('c', $time),
        ];
    }

    public function save(): void {
        $isInsert = $this->getId() < 1;
        if($isInsert) {
            $query = 'INSERT INTO `%1$s%2$s` (`user_id`, `change_action`, `change_log`, `change_text`)'
                . ' VALUES (:user, :action, :header, :body)';
        } else {
            $query = 'UPDATE `%1$s%2$s` SET `user_id` = :user, `change_action` = :action, `change_log` = :header, `change_text` = :body'
                . ' WHERE `change_id` = :change';
        }

        $saveChange = DB::prepare(sprintf($query, DB::PREFIX, self::TABLE))
            ->bind('user', $this->user_id)
            ->bind('action', $this->change_action)
            ->bind('header', $this->change_log)
            ->bind('body', $this->change_text);

        if($isInsert) {
            $this->change_id = $saveChange->executeGetId();
            $this->change_created = time();
        } else {
            $saveChange->bind('change', $this->getId())
                ->execute();
        }
    }

    private static function countQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf('COUNT(%s.`change_id`)', self::TABLE));
    }
    public static function countAll(?int $date = null, ?User $user = null): int {
        $countChanges = DB::prepare(
            self::countQueryBase()
                . ' WHERE 1' // this is still disgusting
                . ($date === null ? '' : ' AND DATE(`change_created`) = :date')
                . ($user === null ? '' : ' AND `user_id` = :user')
        );
        if($date !== null)
            $countChanges->bind('date', gmdate('Y-m-d', $date));
        if($user !== null)
            $countChanges->bind('user', $user->getId());
        return (int)$countChanges->fetchColumn();
    }

    private static function memoizer(): Memoizer {
        static $memoizer = null;
        if($memoizer === null)
            $memoizer = new Memoizer;
        return $memoizer;
    }

    private static function byQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf(self::SELECT, self::TABLE));
    }
    public static function byId(int $changeId): self {
        return self::memoizer()->find($changeId, function() use ($changeId) {
            $change = DB::prepare(self::byQueryBase() . ' WHERE `change_id` = :change')
                ->bind('change', $changeId)
                ->fetchObject(self::class);
            if(!$change)
                throw new ChangelogChangeNotFoundException;
            return $change;
        });
    }
    public static function all(?Pagination $pagination = null, ?int $date = null, ?User $user = null): array {
        $changeQuery = self::byQueryBase()
            . ' WHERE 1' // this is still disgusting
            . ($date === null ? '' : ' AND DATE(`change_created`) = :date')
            . ($user === null ? '' : ' AND `user_id` = :user')
            . ' GROUP BY `change_created`, `change_id`'
            . ' ORDER BY `change_created` DESC, `change_id` DESC';

        if($pagination !== null)
            $changeQuery .= ' LIMIT :range OFFSET :offset';

        $getChanges = DB::prepare($changeQuery);

        if($date !== null)
            $getChanges->bind('date', gmdate('Y-m-d', $date));

        if($user !== null)
            $getChanges->bind('user', $user->getId());

        if($pagination !== null)
            $getChanges->bind('range', $pagination->getRange())
                ->bind('offset', $pagination->getOffset());

        return $getChanges->fetchObjects(self::class);
    }
}
