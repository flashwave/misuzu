<?php
namespace Misuzu\Forum;

use Misuzu\DB;
use Misuzu\Users\User;

class ForumPollAnswer {
    // Database fields
    private $user_id = -1;
    private $poll_id = -1;
    private $option_id = -1;

    public const TABLE = 'forum_polls_answers';
    private const QUERY_SELECT = 'SELECT %1$s FROM `' . DB::PREFIX . self::TABLE . '` AS '. self::TABLE;
    private const SELECT = '%1$s.`user_id`, %1$s.`poll_id`, %1$s.`option_id`';

    private $user = null;
    private $poll = null;
    private $option = null;

    public function getUserId(): int {
        return $this->user_id < 1 ? -1 : $this->user_id;
    }
    public function getUser(): User {
        if($this->user === null)
            $this->user = User::byId($this->getUserId());
        return $this->user;
    }

    public function getPollId(): int {
        return $this->poll_id < 1 ? -1 : $this->poll_id;
    }
    public function getPoll(): ForumPoll {
        if($this->poll === null)
            $this->poll = ForumPoll::byId($this->getPollId());
        return $this->poll;
    }

    public function getOptionId(): int {
        return $this->option_id < 1 ? -1 : $this->option_id;
    }
    public function getOption(): ForumPollOption {
        if($this->option === null)
            $this->option = ForumPollOption::byId($this->getOptionId());
        return $this->option;
    }

    public function hasAnswer(): bool {
        return $this->getOptionId() > 0;
    }

    private static function countQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf('COUNT(*)', self::TABLE));
    }
    public static function countByPoll(ForumPoll $poll): int {
        return (int)DB::prepare(
            self::countQueryBase() . ' WHERE `poll_id` = :poll'
        )->bind('poll', $poll->getId())->fetchColumn();
    }
    public static function countByOption(ForumPollOption $option): int {
        return (int)DB::prepare(
            self::countQueryBase() . ' WHERE `option_id` = :option'
        )->bind('option', $option->getId())->fetchColumn();
    }

    private static function byQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf(self::SELECT, self::TABLE));
    }
    public static function byExact(User $user, ForumPoll $poll): array {
        return DB::prepare(self::byQueryBase() . ' WHERE `poll_id` = :poll AND `user_id` = :user')
            ->bind('user', $user->getId())
            ->bind('poll', $poll->getId())
            ->fetchObjects(self::class);
    }
}
