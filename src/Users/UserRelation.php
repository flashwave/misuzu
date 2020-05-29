<?php
namespace Misuzu\Users;

use Misuzu\DB;
use Misuzu\Pagination;

class UserRelation {
    public const TYPE_NONE = 0;
    public const TYPE_FOLLOW = 1;

    // Database fields
    private $user_id = -1;
    private $subject_id = -1;
    private $relation_type = 0;
    private $relation_created = null;

    private $user = null;
    private $subject = null;

    public const TABLE = 'user_relations';
    private const QUERY_SELECT = 'SELECT %1$s FROM `' . DB::PREFIX . self::TABLE . '` AS '. self::TABLE;
    private const SELECT = '%1$s.`user_id`, %1$s.`subject_id`, %1$s.`relation_type`'
        . ', UNIX_TIMESTAMP(%1$s.`relation_created`) AS `relation_created`';

    public function getUserId(): int {
        return $this->user_id < 1 ? -1 : $this->user_id;
    }
    public function getUser(): User {
        if($this->user === null)
            $this->user = User::byId($this->getUserId());
        return $this->user;
    }

    public function getSubjectId(): int {
        return $this->subject_id < 1 ? -1 : $this->subject_id;
    }
    public function getSubject(): User {
        if($this->subject === null)
            $this->subject = User::byId($this->getSubjectId());
        return $this->subject;
    }

    public function getType(): int {
        return $this->relation_type;
    }
    public function isNone(): bool {
        return $this->getType() === self::TYPE_NONE;
    }
    public function isFollow(): bool {
        return $this->getType() === self::TYPE_FOLLOW;
    }

    public function getCreationTime(): int {
        return $this->relation_created === null ? -1 : $this->relation_created;
    }

    public static function destroy(User $user, User $subject): void {
        DB::prepare('DELETE FROM `' . DB::PREFIX . self::TABLE . '` WHERE `user_id` = :user AND `subject_id` = :subject')
            ->bind('user', $user->getId())
            ->bind('subject', $subject->getId())
            ->execute();
    }

    public static function create(User $user, User $subject, int $type): void {
        if($type === self::TYPE_NONE) {
            self::destroy($user, $subject);
            return;
        }

        DB::prepare(
            'REPLACE INTO `' . DB::PREFIX . self::TABLE . '` (`user_id`, `subject_id`, `relation_type`)'
            . ' VALUES (:user, :subject, :type)'
        )   ->bind('user', $user->getId())
            ->bind('subject', $subject->getId())
            ->bind('type', $type)
            ->execute();
    }

    private static function countQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf('COUNT(*)', self::TABLE));
    }
    public static function countByUser(User $user, ?int $type = null): int {
        $count = DB::prepare(
            self::countQueryBase()
            . ' WHERE `user_id` = :user'
            . ($type === null ? '' : ' AND `relation_type` = :type')
        )   ->bind('user', $user->getId());
        if($type !== null)
            $count->bind('type', $type);
        return (int)$count->fetchColumn();
    }
    public static function countBySubject(User $subject, ?int $type = null): int {
        $count = DB::prepare(
            self::countQueryBase()
            . ' WHERE `subject_id` = :subject'
            . ($type === null ? '' : ' AND `relation_type` = :type')
        )   ->bind('subject', $subject->getId());
        if($type !== null)
            $count->bind('type', $type);
        return (int)$count->fetchColumn();
    }

    private static function byQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf(self::SELECT, self::TABLE));
    }
    public static function byUserAndSubject(User $user, User $subject): self {
        $object = DB::prepare(self::byQueryBase() . ' WHERE `user_id` = :user AND `subject_id` = :subject')
            ->bind('user', $user->getId())
            ->bind('subject', $subject->getId())
            ->fetchObject(self::class);

        if(!$object) {
            $fake = new static;
            $fake->user_id = $user->getId();
            $fake->user = $user;
            $fake->subject_id = $subject->getId();
            $fake->subject = $subject;
            return $fake;
        }

        return $object;
    }
    public static function byUser(User $user, ?int $type = null, ?Pagination $pagination = null): array {
        $query = self::byQueryBase()
                . ' WHERE `user_id` = :user'
                . ($type === null ? '' : ' AND `relation_type` = :type')
                . ' ORDER BY `relation_created` DESC';

        if($pagination !== null)
            $query .= ' LIMIT :range OFFSET :offset';

        $get = DB::prepare($query)
            ->bind('user', $user->getId());

        if($type !== null)
            $get->bind('type', $type);

        if($pagination !== null)
            $get->bind('range', $pagination->getRange())
                ->bind('offset', $pagination->getOffset());

        return $get->fetchObjects(self::class);
    }
    public static function bySubject(User $subject, ?int $type = null, ?Pagination $pagination = null): array {
        $query = self::byQueryBase()
                . ' WHERE `subject_id` = :subject'
                . ($type === null ? '' : ' AND `relation_type` = :type')
                . ' ORDER BY `relation_created` DESC';

        if($pagination !== null)
            $query .= ' LIMIT :range OFFSET :offset';

        $get = DB::prepare($query)
            ->bind('subject', $subject->getId());

        if($type !== null)
            $get->bind('type', $type);

        if($pagination !== null)
            $get->bind('range', $pagination->getRange())
                ->bind('offset', $pagination->getOffset());

        return $get->fetchObjects(self::class);
    }
}
