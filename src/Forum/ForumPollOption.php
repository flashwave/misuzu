<?php
namespace Misuzu\Forum;

use Misuzu\DB;
use Misuzu\Users\User;

class ForumPollOption {
    // Database fields
    private $option_id = -1;
    private $poll_id = -1;
    private $option_text = null;

    public const TABLE = 'forum_polls_options';
    private const QUERY_SELECT = 'SELECT %1$s FROM `' . DB::PREFIX . self::TABLE . '` AS '. self::TABLE;
    private const SELECT = '%1$s.`option_id`, %1$s.`poll_id`, %1$s.`option_text`';

    private $poll = null;
    private $voteCount = -1;

    public function getId(): int {
        return $this->option_id < 1 ? -1 : $this->option_id;
    }

    public function getPollId(): int {
        return $this->poll_id < 1 ? -1 : $this->poll_id;
    }
    public function getPoll(): ForumPoll {
        if($this->poll === null)
            $this->poll = ForumPoll::byId($this->getPollId());
        return $this->poll;
    }

    public function getText(): string {
        return $this->option_text ?? '';
    }

    public function getPercentage(): float {
        return $this->getVotes() / $this->getPoll()->getVotes();
    }

    public function getVotes(): int {
        if($this->voteCount < 0)
            $this->voteCount = ForumPollAnswer::countByOption($this);
        return $this->voteCount;
    }

    public function hasVotedFor(?User $user): bool {
        if($user === null)
            return false;
        return array_find($this->getPoll()->getAnswers($user), function($answer) {
            return $answer->getOptionId() === $this->getId();
        }) !== null;
    }

    private static function byQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf(self::SELECT, self::TABLE));
    }
    public static function byPoll(ForumPoll $poll): array {
        return DB::prepare(self::byQueryBase() . ' WHERE `poll_id` = :poll')
            ->bind('poll', $poll->getId())
            ->fetchObjects(self::class);
    }
}
