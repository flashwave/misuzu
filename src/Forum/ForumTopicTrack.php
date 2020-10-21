<?php
namespace Misuzu\Forum;

use Misuzu\DB;
use Misuzu\Memoizer;
use Misuzu\Users\User;

class ForumTopicTrackException extends ForumException {}
class ForumTopicTrackNotFoundException extends ForumTopicTrackException {}

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

    public static function bump(ForumTopic $topic, User $user): void {
        DB::prepare(
            'REPLACE INTO `' . DB::PREFIX . self::TABLE . '`'
            . ' (`user_id`, `topic_id`, `forum_id`, `track_last_read`)'
            . ' VALUES (:user, :topic, :forum, NOW())'
        )->bind('user', $user->getId())
         ->bind('topic', $topic->getId())
         ->bind('forum', $topic->getCategoryId())
         ->execute();
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
    public static function byTopicAndUser(ForumTopic $topic, User $user): ForumTopicTrack {
        return self::memoizer()->find(function($track) use ($topic, $user) {
            return $track->getTopicId() === $topic->getId() && $track->getUserId() === $user->getId();
        }, function() use ($topic, $user) {
            $obj = DB::prepare(self::byQueryBase() . ' WHERE `topic_id` = :topic AND `user_id` = :user')
                ->bind('topic', $topic->getId())
                ->bind('user', $user->getId())
                ->fetchObject(self::class);
            if(!$obj)
                throw new ForumTopicTrackNotFoundException;
            return $obj;
        });
    }
    public static function byCategoryAndUser(ForumCategory $category, User $user): ForumTopicTrack {
        return self::memoizer()->find(function($track) use ($category, $user) {
            return $track->getCategoryId() === $category->getId() && $track->getUserId() === $user->getId();
        }, function() use ($category, $user) {
            $obj = DB::prepare(self::byQueryBase() . ' WHERE `forum_id` = :category AND `user_id` = :user')
                ->bind('category', $category->getId())
                ->bind('user', $user->getId())
                ->fetchObject(self::class);
            if(!$obj)
                throw new ForumTopicTrackNotFoundException;
            return $obj;
        });
    }
}
