<?php
namespace Misuzu\Forum;

use Misuzu\DB;
use Misuzu\Users\User;

class ForumTopicTrack {
    // Database fields
    private $user_id = -1;
    private $topic_id = -1;
    private $forum_id = -1;
    private $track_last_read = null;

    public const TABLE = 'forum_topics_track';
    private const QUERY_SELECT = 'SELECT %1$s FROM `' . DB::PREFIX . self::TABLE . '` AS '. self::TABLE;
    private const SELECT = '%1$s.`user_id`, %1$s.`topic_id`, %1$s.`forum_id`'
                         . ', UNIX_TIMESTAMP(%1$s.`track_last_read`) AS `track_last_read`';

    private $user = null;
    private $topic = null;
    private $category = null;

    public function getUserId(): int {
        return $this->user_id < 1 ? -1 : $this->user_id;
    }
    public function getUser(): User {
        if($this->user === null)
            $this->user = User::byId($this->getUserId());
        return $this->user;
    }

    public function getTopicId(): int {
        return $this->topic_id < 1 ? -1 : $this->topic_id;
    }
    public function getTopic(): ForumTopic {
        if($this->topic === null)
            $this->topic = ForumTopic::byId($this->getTopicId());
        return $this->topic;
    }

    public function getCategoryId(): int {
        return $this->forum_id < 1 ? -1 : $this->forum_id;
    }
    public function getCategory(): ForumCategory {
        if($this->category === null)
            $this->category = ForumCategory::byId($this->getCategoryId());
        return $this->category;
    }

    public function getReadTime(): int {
        return $this->track_last_read === null ? -1 : $this->track_last_read;
    }
}
