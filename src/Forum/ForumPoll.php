<?php
namespace Misuzu\Forum;

use Misuzu\DB;
use Misuzu\Memoizer;
use Misuzu\Users\User;

class ForumPollException extends ForumException {}
class ForumPollNotFoundException extends ForumPollException {}

class ForumPoll {
    // Database fields
    private $poll_id = -1;
    private $topic_id = null;
    private $poll_max_votes = 0;
    private $poll_expires = null;
    private $poll_preview_results = 0;
    private $poll_change_vote = 0;

    public const TABLE = 'forum_polls';
    private const QUERY_SELECT = 'SELECT %1$s FROM `' . DB::PREFIX . self::TABLE . '` AS '. self::TABLE;
    private const SELECT = '%1$s.`poll_id`, %1$s.`topic_id`, %1$s.`poll_max_votes`, %1$s.`poll_preview_results`, %1$s.`poll_change_vote`'
                         . ', UNIX_TIMESTAMP(%1$s.`poll_expires`) AS `poll_expires`';

    private $topic = null;
    private $options = null;
    private $answers = [];
    private $voteCount = -1;

    public function getId(): int {
        return $this->poll_id < 1 ? -1 : $this->poll_id;
    }

    public function getTopicId(): int {
        return $this->topic_id < 1 ? -1 : $this->topic_id;
    }
    public function setTopicId(?int $topicId): self {
        $this->topic_id = $topicId < 1 ? null : $topicId;
        $this->topic = null;
        return $this;
    }
    public function hasTopic(): bool {
        return $this->getTopicId() > 0;
    }
    public function getTopic(): ForumTopic {
        if($this->topic === null)
            $this->topic = ForumTopic::byId($this->getTopicId());
        return $this->topic;
    }
    public function setTopic(?ForumTopic $topic): self {
        $this->topic_id = $topic === null ? null : $topic->getId();
        $this->topic = $topic;
        return $this;
    }

    public function getMaxVotes(): int {
        return max(0, $this->poll_max_votes);
    }
    public function setMaxVotes(int $maxVotes): self {
        $this->poll_max_votes = max(0, $maxVotes);
        return $this;
    }

    public function getExpiresTime(): int {
        return $this->poll_expires === null ? -1 : $this->poll_expires;
    }
    public function hasExpired(): bool {
        return $this->getExpiresTime() >= time();
    }
    public function canExpire(): bool {
        return $this->getExpiresTime() >= 0;
    }
    public function setExpiresTime(int $expires): self {
        $this->poll_expires = $expires < 0 ? null : $expires;
        return $this;
    }

    public function canPreviewResults(): bool {
        return boolval($this->poll_preview_results);
    }
    public function setPreviewResults(bool $canPreview): self {
        $this->poll_preview_results = $canPreview ? 1 : 0;
        return $this;
    }

    public function canChangeVote(): bool {
        return boolval($this->poll_change_vote);
    }
    public function setChangeVote(bool $canChange): self {
        $this->poll_change_vote = $canChange ? 1 : 0;
        return $this;
    }

    public function getVotes(): int {
        if($this->voteCount < 0)
            $this->voteCount = ForumPollAnswer::countByPoll($this);
        return $this->voteCount;
    }

    public function getOptions(): array {
        if($this->options === null)
            $this->options = ForumPollOption::byPoll($this);
        return $this->options;
    }

    public function getAnswers(?User $user): array {
        if($user === null)
            return [];
        $userId = $user->getId();
        if(!isset($this->answers[$userId]))
            $this->answers[$userId] = ForumPollAnswer::byExact($user, $this);
        return $this->answers[$userId];
    }
    public function hasVoted(?User $user): bool {
        if($user === null)
            return false;
        $userId = $user->getId();
        if(!isset($this->answers[$userId]))
            return !empty($this->getAnswers($user));
        return !empty($this->answers[$userId]);
    }

    public function canVoteOnPoll(?User $user): bool {
        if($user === null)
            return false;
        if(!$this->hasTopic()) // introduce generic poll vote permission?
            return true;
        return forum_perms_check_user(
            MSZ_FORUM_PERMS_GENERAL,
            $this->getTopic()->getCategory()->getId(),
            $user->getId(),
            MSZ_FORUM_PERM_SET_READ
        );
    }

    private static function memoizer() {
        static $memoizer = null;
        if($memoizer === null)
            $memoizer = new Memoizer;
        return $memoizer;
    }

    private static function byQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf(self::SELECT, self::TABLE));
    }
    public static function byId(int $pollId): self {
        return self::memoizer()->find($pollId, function() use ($pollId) {
            $object = DB::prepare(self::byQueryBase() . ' WHERE `poll_id` = :poll')
                ->bind('poll', $pollId)
                ->fetchObject(self::class);
            if(!$object)
                throw new ForumPollNotFoundException;
            return $object;
        });
    }
    public static function byTopic(ForumTopic $topic): array {
        $getObjects = DB::prepare(self::byQueryBase() . ' WHERE `topic_id` = :topic')
            ->bind('topic', $topic->getId());

        $objects = [];
        $memoizer = self::memoizer();
        while($object = $getObjects->fetchObject(self::class))
            $memoizer->insert($objects[] = $object);
        return $objects;
    }
}
