<?php
namespace Misuzu\Forum;

use Misuzu\DB;
use Misuzu\Users\User;

class ForumTopicPriority {
    // Database fields
    private $topic_id = -1;
    private $user_id = -1;
    private $topic_priority = 0;

    public const TABLE = 'forum_topics_priority';
    private const QUERY_SELECT = 'SELECT %1$s FROM `' . DB::PREFIX . self::TABLE . '` AS '. self::TABLE;
    private const SELECT = '%1$s.`topic_id`, %1$s.`user_id`, %1$s.`topic_priority`';

    private $topic = null;
    private $user = null;

    public function getTopicId(): int {
        return $this->topic_id < 1 ? -1 : $this->topic_id;
    }
    public function getTopic(): ForumTopic {
        if($this->topic === null)
            $this->topic = ForumTopic::byId($this->getTopicId());
        return $this->topic;
    }

    public function getUserId(): int {
        return $this->user_id < 1 ? -1 : $this->user_id;
    }
    public function getUser(): User {
        if($this->user === null)
            $this->user = User::byId($this->getUserId());
        return $this->user;
    }

    public function getPriority(): int {
        return $this->topic_priority < 1 ? -1 : $this->topic_priority;
    }

    private static function byQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf(self::SELECT, self::TABLE));
    }
    public static function byTopic(ForumTopic $topic): array {
        return DB::prepare(self::byQueryBase() . ' WHERE `topic_id` = :topic')
            ->bind('topic', $topic->getId())
            ->fetchObjects(self::class);
    }
}
