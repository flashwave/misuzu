<?php
namespace Misuzu\Forum;

use Misuzu\DB;
use Misuzu\Memoizer;
use Misuzu\Pagination;
use Misuzu\Users\User;

class ForumTopicException extends ForumException {}
class ForumTopicNotFoundException extends ForumTopicException {}
class ForumTopicCreationFailedException extends ForumTopicException {}
class ForumTopicUpdateFailedException extends ForumTopicException {}

class ForumTopic {
    public const TYPE_DISCUSSION = 0;
    public const TYPE_STICKY = 1;
    public const TYPE_ANNOUNCEMENT = 2;
    public const TYPE_GLOBAL_ANNOUNCEMENT = 3;

    public const TYPES = [
        self::TYPE_DISCUSSION,
        self::TYPE_STICKY,
        self::TYPE_ANNOUNCEMENT,
        self::TYPE_GLOBAL_ANNOUNCEMENT,
    ];

    public const TYPE_ORDER = [
        self::TYPE_GLOBAL_ANNOUNCEMENT,
        self::TYPE_ANNOUNCEMENT,
        self::TYPE_STICKY,
        self::TYPE_DISCUSSION,
    ];

    public const TYPE_IMPORTANT = [
        self::TYPE_STICKY,
        self::TYPE_ANNOUNCEMENT,
        self::TYPE_GLOBAL_ANNOUNCEMENT,
    ];

    public const TITLE_MIN_LENGTH = 3;
    public const TITLE_MAX_LENGTH = 100;

    public const DELETE_AGE_LIMIT = 60 * 60 * 24;
    public const DELETE_POST_LIMIT = 2;

    // Database fields
    private $topic_id = -1;
    private $forum_id = -1;
    private $user_id = null;
    private $topic_type = self::TYPE_DISCUSSION;
    private $topic_title = '';
    private $topic_priority = 0;
    private $topic_count_posts = 0;
    private $topic_count_views = 0;
    private $topic_post_first = null;
    private $topic_post_last = null;
    private $topic_created = null;
    private $topic_bumped = null;
    private $topic_deleted = null;
    private $topic_locked = null;

    public const TABLE = 'forum_topics';
    private const QUERY_SELECT = 'SELECT %1$s FROM `' . DB::PREFIX . self::TABLE . '` AS '. self::TABLE;
    private const SELECT = '%1$s.`topic_id`, %1$s.`forum_id`, %1$s.`user_id`, %1$s.`topic_type`, %1$s.`topic_title`'
                         . ', %1$s.`topic_count_posts`, %1$s.`topic_count_views`, %1$s.`topic_post_first`, %1$s.`topic_post_last`'
                         . ', UNIX_TIMESTAMP(%1$s.`topic_created`) AS `topic_created`'
                         . ', UNIX_TIMESTAMP(%1$s.`topic_bumped`) AS `topic_bumped`'
                         . ', UNIX_TIMESTAMP(%1$s.`topic_deleted`) AS `topic_deleted`'
                         . ', UNIX_TIMESTAMP(%1$s.`topic_locked`) AS `topic_locked`';

    private $category = null;
    private $user = null;
    private $firstPost = -1;
    private $lastPost = -1;
    private $priorityVotes = null;
    private $polls = [];

    public function getId(): int {
        return $this->topic_id < 1 ? -1 : $this->topic_id;
    }

    public function getCategoryId(): int {
        return $this->forum_id < 1 ? -1 : $this->forum_id;
    }
    public function setCategoryId(int $categoryId): self {
        $this->forum_id = $categoryId;
        $this->category = null;
        return $this;
    }
    public function getCategory(): ForumCategory {
        if($this->category === null)
            $this->category = ForumCategory::byId($this->getCategoryId());
        return $this->category;
    }
    public function setCategory(ForumCategory $category): self {
        $this->forum_id = $category->getId();
        $this->category = $category;
        return $this;
    }

    public function getUserId(): int {
        return $this->user_id < 1 ? -1 : $this->user_id;
    }
    public function setUserId(?int $userId): self {
        $this->user_id = $userId < 1 ? null : $userId;
        $this->user = null;
        return $this;
    }
    public function getUser(): ?User {
        if($this->user === null && ($userId = $this->getUserId()) > 0)
            $this->user = User::byId($userId);
        return $this->user;
    }
    public function hasUser(): bool {
        return $this->getUserId() > 0;
    }
    public function setUser(?User $user): self {
        $this->user_id = $user === null ? null : $user->getId();
        $this->user = $user;
        return $this;
    }

    public function getType(): int {
        return $this->topic_type;
    }
    public function setType(int $type): self {
        $this->topic_type = $type;
        return $this;
    }
    public function isNormal(): bool             { return $this->getType() === self::TYPE_DISCUSSION; }
    public function isSticky(): bool             { return $this->getType() === self::TYPE_STICKY; }
    public function isAnnouncement(): bool       { return $this->getType() === self::TYPE_ANNOUNCEMENT; }
    public function isGlobalAnnouncement(): bool { return $this->getType() === self::TYPE_GLOBAL_ANNOUNCEMENT; }

    public function isImportant(): bool {
        return in_array($this->getType(), self::TYPE_IMPORTANT);
    }

    public function hasPriorityVoting(): bool {
        return $this->getCategory()->canHavePriorityVotes();
    }

    public function getIcon(?User $viewer = null): string {
        if($this->isDeleted())
            return 'fas fa-trash-alt fa-fw';

        if($this->isGlobalAnnouncement() || $this->isAnnouncement())
            return 'fas fa-bullhorn fa-fw';
        if($this->isSticky())
            return 'fas fa-thumbtack fa-fw';

        if($this->isLocked())
            return 'fas fa-lock fa-fw';

        if($this->hasPriorityVoting())
            return 'far fa-star fa-fw';

        return ($this->hasUnread($viewer) ? 'fas' : 'far') . ' fa-comment fa-fw';
    }

    public function getTitle(): string {
        return $this->topic_title ?? '';
    }
    public function setTitle(string $title): self {
        $this->topic_title = $title;
        return $this;
    }

    public function getPriority(): int {
        return $this->topic_priority < 1 ? 0 : $this->topic_priority;
    }

    public function getPostCount(): int {
        return $this->topic_count_posts;
    }
    public function getPageCount(int $postsPerPage = 10): int {
        return ceil($this->getPostCount() / $postsPerPage);
    }

    public function getViewCount(): int {
        return $this->topic_count_views;
    }

    public function getFirstPostId(): int {
        return $this->topic_post_first < 1 ? -1 : $this->topic_post_first;
    }
    public function hasFirstPost(): bool {
        return $this->getFirstPostId() > 0;
    }
    public function getFirstPost(): ?ForumPost {
        if($this->firstPost === -1) {
            if(!$this->hasFirstPost())
                return null;
            try {
                $this->firstPost = ForumPost::byId($this->getFirstPostId());
            } catch(ForumPostNotFoundException $ex) {
                $this->firstPost = null;
            }
        }
        return $this->firstPost;
    }

    public function getLastPostId(): int {
        return $this->topic_post_last < 1 ? -1 : $this->topic_post_last;
    }
    public function hasLastPost(): bool {
        return $this->getLastPostId() > 0;
    }
    public function getLastPost(): ?ForumPost {
        if($this->lastPost === -1) {
            if(!$this->hasLastPost())
                return null;
            try {
                $this->lastPost = ForumPost::byId($this->getLastPostId());
            } catch(ForumPostNotFoundException $ex) {
                $this->lastPost = null;
            }
        }
        return $this->lastPost;
    }

    public function getCreatedTime(): int {
        return $this->topic_created === null ? -1 : $this->topic_created;
    }

    public function getBumpedTime(): int {
        return $this->topic_bumped === null ? -1 : $this->topic_bumped;
    }
    public function bumpTopic(): void {
        if($this->isDeleted())
            return;
        $this->topic_bumped = time();
        DB::prepare(
            'UPDATE `' . DB::PREFIX . self::TABLE . '`'
            . ' SET `topic_bumped` = NOW()'
            . ' WHERE `topic_id` = :topic'
            . ' AND `topic_deleted` IS NULL'
        )->bind('topic', $this->getId())->execute();
    }

    public function getDeletedTime(): int {
        return $this->topic_deleted === null ? -1 : $this->topic_deleted;
    }
    public function isDeleted(): bool {
        return $this->getDeletedTime() >= 0;
    }

    public function getLockedTime(): int {
        return $this->topic_locked === null ? -1 : $this->topic_locked;
    }
    public function isLocked(): bool {
        return $this->getLockedTime() >= 0;
    }
    public function setLocked(bool $locked): self {
        if($this->isLocked() !== $locked)
            $this->topic_locked = $locked ? time() : null;
        return $this;
    }

    public function isArchived(): bool {
        return $this->getCategory()->isArchived();
    }

    public function getActualPostCount(bool $includeDeleted = false): int {
        return ForumPost::countByTopic($this, $includeDeleted);
    }
    public function getPosts(bool $includeDeleted = false, ?Pagination $pagination = null): array {
        return ForumPost::byTopic($this, $includeDeleted, $pagination);
    }

    public function getPolls(): array {
        if($this->polls === null)
            $this->polls = ForumPoll::byTopic($this);
        return $this->polls;
    }

    public function hasUnread(?User $user): bool {
        if($user === null)
            return false;
        return mt_rand(0, 10) >= 5;
    }

    public function hasParticipated(?User $user): bool {
        return $user !== null;
    }

    public function isOpeningPost(ForumPost $post): bool {
        $firstPost = $this->getFirstPost();
        return $firstPost !== null && $firstPost->getId() === $post->getId();
    }
    public function isTopicAuthor(?User $user): bool {
        if($user === null)
            return false;
        return $user->getId() === $this->getUser()->getId();
    }

    public function getPriorityVotes(): array {
        if($this->priorityVotes === null)
            $this->priorityVotes = ForumTopicPriority::byTopic($this);
        return $this->priorityVotes;
    }

    public function canVoteOnPriority(?User $user): bool {
        if($user === null || !$this->hasPriorityVoting())
            return false;
        // shouldn't there be an actual permission for this?
        return $this->getCategory()->canView($user);
    }

    public function canDelete(User $user): string {
        if(false) // check if viewable
            return 'view';

        // check if user can view deleted posts/is mod
        $canDeleteAny = false;

        if($this->isDeleted())
            return $canDeleteAny ? 'deleted' : 'view';

        if(!$canDeleteAny) {
            if(false) // check if user can delete posts
                return 'permission';
            if($user->getId() !== $this->getUserId())
                return 'owner';
            if($this->getCreatedTime() <= time() - self::DELETE_AGE_LIMIT)
                return 'age';
            if($this->getActualPostCount(true) >= self::DELETE_POST_LIMIT)
                return 'posts';
        }

        return '';
    }
    public static function canDeleteErrorString(string $error): string {
        switch($error) {
            case 'view':
                return 'This topic doesn\'t exist.';
            case 'deleted':
                return 'This topic has already been marked as deleted.';
            case 'permission':
                return 'You aren\'t allowed to this topic.';
            case 'owner':
                return 'You can only delete your own topics.';
            case 'age':
                return 'This topic is too old to be deleted. Ask a moderator to remove it if you deem it absolutely necessary.';
            case 'posts':
                return 'This topic has too many replies to be deleted. Ask a moderator to remove it if you deem it absolutely necessary.';
            case '':
                return 'Topic can be deleted!';
            default:
                return 'Topic cannot be deleted.';
        }
    }

    public function delete(): void {
        if($this->isDeleted())
            return;
        $this->topic_deleted = time();
        DB::prepare('UPDATE `' . DB::PREFIX . self::TABLE . '` SET `topic_deleted` = NOW() WHERE `topic_id` = :topic')
            ->bind('topic', $this->getId())
            ->execute();
        ForumPost::deleteTopic($this);
    }
    public function restore(): void {
        if(!$this->isDeleted())
            return;
        ForumPost::restoreTopic($this);
        DB::prepare('UPDATE `' . DB::PREFIX . self::TABLE . '` SET `topic_deleted` = NULL WHERE `topic_id` = :topic')
            ->bind('topic', $this->getId())
            ->execute();
        $this->topic_deleted = null;
    }
    public function nuke(): void {
        if(!$this->isDeleted())
            return;
        DB::prepare('DELETE FROM `' . DB::PREFIX . self::TABLE . '` WHERE `topic_id` = :topic')
            ->bind('topic', $this->getId())
            ->execute();
        //ForumPost::nukeTopic($this);
    }

    public static function create(ForumCategory $category, User $user, string $title, int $type = self::TYPE_DISCUSSION): ForumTopic {
        $create = DB::prepare(
            'INSERT INTO `msz_forum_topics` (`forum_id`, `user_id`, `topic_title`, `topic_type`) VALUES (:forum, :user, :title, :type)'
        )->bind('forum', $category->getId())->bind('user', $user->getId())
         ->bind('title', $title)->bind('type', $type)
         ->execute();
        if(!$create)
            throw new ForumTopicCreationFailedException;
        $topicId = DB::lastId();
        if($topicId < 1)
            throw new ForumTopicCreationFailedException;

        try {
            return self::byId($topicId);
        } catch(ForumTopicNotFoundException $ex) {
            throw new ForumTopicCreationFailedException;
        }
    }

    public function update(): void {
        if($this->getId() < 1)
            throw new ForumTopicUpdateFailedException;

        if(!DB::prepare(
            'UPDATE `msz_forum_topics`'
            . ' SET `topic_title` = :title,'
            .     ' `topic_type` = :type'
            . ' WHERE `topic_id` = :topic'
        )->bind('topic', $this->getId())
         ->bind('title', $this->getTitle())
         ->bind('type', $this->getType())
         ->execute())
            throw new ForumTopicUpdateFailedException;
    }

    public function synchronise(bool $save = true): array {
        $stats = DB::prepare(
            'SELECT :topic AS `topic`, ('
            .  'SELECT MIN(`post_id`) FROM `msz_forum_posts` WHERE `topic_id` = `topic`' // this shouldn't be deleteable without nuking the topic
            . ') AS `first_post`, ('
            .  'SELECT MAX(`post_id`) FROM `msz_forum_posts` WHERE `topic_id` = `topic` AND `post_deleted` IS NULL'
            . ') AS `last_post`, ('
            .  'SELECT COUNT(*) FROM `msz_forum_posts` WHERE `topic_id` = `topic` AND `post_deleted` IS NULL'
            . ') AS `posts`, ('
            .  'SELECT UNIX_TIMESTAMP(`post_created`) FROM `msz_forum_posts` WHERE `post_id` = `last_post`'
            . ') AS `last_post_time`'
        )->bind('topic', $this->getId())->fetch();

        if($save) {
            $this->topic_post_first = $stats['first_post'];
            $this->topic_post_last = $stats['last_post'];
            $this->topic_count_posts = $stats['posts'];
            DB::prepare(
                'UPDATE `msz_forum_topics`'
                . ' SET `topic_post_first` = :first'
                . ', `topic_post_last` = :last'
                . ', `topic_count_posts` = :posts'
                . ' WHERE `topic_id` = :topic'
            )   ->bind('first', $this->topic_post_first)
                ->bind('last', $this->topic_post_last)
                ->bind('posts', $this->topic_count_posts)
                ->bind('topic', $this->getId())
                ->execute();
        }

        return $stats;
    }

    public static function validateTitle(string $title): string {
        $length = mb_strlen(trim($title));
        if($length < self::TITLE_MIN_LENGTH)
            return 'short';
        if($length > self::TITLE_MAX_LENGTH)
            return 'long';
        return '';
    }
    public static function titleValidationErrorString(string $error): string {
        switch($error) {
            case 'short':
                return sprintf('Topic title was too short, it has to be at least %d characters!', self::TITLE_MIN_LENGTH);
            case 'long':
                return sprintf("Topic title was too long, it can't be longer than %d characters!", self::TITLE_MAX_LENGTH);
            case '':
                return 'Topic title is correctly formatted!';
            default:
                return 'Topic title is incorrectly formatted.';
        }
    }

    private static function countQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf('COUNT(*)', self::TABLE));
    }
    public static function countByCategory(ForumCategory $category, bool $includeDeleted = false): int {
        return (int)DB::prepare(
            self::countQueryBase()
            . ' WHERE `forum_id` = :category'
            . ($includeDeleted ? '' : ' AND `topic_deleted` IS NULL')
        )->bind('category', $category->getId())->fetchColumn();
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
    public static function byId(int $topicId): self {
        return self::memoizer()->find($topicId, function() use ($topicId) {
            $object = DB::prepare(self::byQueryBase() . ' WHERE `topic_id` = :topic')
                ->bind('topic', $topicId)
                ->fetchObject(self::class);
            if(!$object)
                throw new ForumTopicNotFoundException;
            return $object;
        });
    }
    public static function byCategoryLast(ForumCategory $category): ?self {
        return self::memoizer()->find(function($topic) use ($category) {
            // This doesn't actually do what is advertised, but should be fine for the time being.
            return $topic->getCategory()->getId() === $category->getId() && !$topic->isDeleted();
        }, function() use ($category) {
            return DB::prepare(
                self::byQueryBase()
                . ' WHERE `forum_id` = :category AND `topic_deleted` IS NULL'
                . ' ORDER BY `topic_bumped` DESC'
                . ' LIMIT 1'
            )->bind('category', $category->getId())->fetchObject(self::class);
        });
    }
    public static function byCategory(ForumCategory $category, bool $includeDeleted = false, ?Pagination $pagination = null): array {
        if(!$category->canHaveTopics())
            return [];

        $query = self::byQueryBase()
                . ' WHERE `forum_id` = :category'
                . ($includeDeleted ? '' : ' AND `topic_deleted` IS NULL')
                . ' ORDER BY FIELD(`topic_type`, ' . implode(',', self::TYPE_ORDER) . ')';

        //if($category->canHavePriorityVotes())
        //    $query .= ', `topic_priority` DESC';

        $query .= ', `topic_bumped` DESC';

        if($pagination !== null)
            $query .= ' LIMIT :range OFFSET :offset';

        $getObjects = DB::prepare($query)
            ->bind('category', $category->getId());

        if($pagination !== null)
            $getObjects->bind('range', $pagination->getRange())
                ->bind('offset', $pagination->getOffset());

        $objects = [];
        $memoizer = self::memoizer();
        while($object = $getObjects->fetchObject(self::class))
            $memoizer->insert($objects[] = $object);
        return $objects;
    }
    public static function bySearchQuery(string $search, bool $includeDeleted = false, ?Pagination $pagination = null): array {
        $query = self::byQueryBase()
                . ' WHERE MATCH(`topic_title`) AGAINST (:search IN NATURAL LANGUAGE MODE)'
                . ($includeDeleted ? '' : ' AND `topic_deleted` IS NULL')
                . ' ORDER BY FIELD(`topic_type`, ' . implode(',', self::TYPE_ORDER) . '), `topic_bumped` DESC';

        if($pagination !== null)
            $query .= ' LIMIT :range OFFSET :offset';

        $getObjects = DB::prepare($query)
            ->bind('search', $search);

        if($pagination !== null)
            $getObjects->bind('range', $pagination->getRange())
                ->bind('offset', $pagination->getOffset());

        $objects = [];
        $memoizer = self::memoizer();
        while($object = $getObjects->fetchObject(self::class))
            $memoizer->insert($objects[] = $object);
        return $objects;
    }
}
