<?php
namespace Misuzu\Forum;

use Misuzu\DB;
use Misuzu\Memoizer;
use Misuzu\Pagination;
use Misuzu\Parsers\Parser;
use Misuzu\Users\User;
use Misuzu\Users\UserNotFoundException;

class ForumPostException extends ForumException {}
class ForumPostNotFoundException extends ForumPostException {}
class ForumPostCreationFailedException extends ForumPostException {}

class ForumPost {
    public const PER_PAGE = 10;

    // Database fields
    private $post_id = -1;
    private $topic_id = -1;
    private $forum_id = -1;
    private $user_id = null;
    private $post_ip = '::1';
    private $post_text = '';
    private $post_parse = Parser::BBCODE;
    private $post_display_signature = 1;
    private $post_created = null;
    private $post_edited = null;
    private $post_deleted = null;

    public const TABLE = 'forum_posts';
    private const QUERY_SELECT = 'SELECT %1$s FROM `' . DB::PREFIX . self::TABLE . '` AS '. self::TABLE;
    private const SELECT = '%1$s.`post_id`, %1$s.`topic_id`, %1$s.`forum_id`, %1$s.`user_id`, %1$s.`post_text`, %1$s.`post_parse`, %1$s.`post_display_signature`'
                         . ', INET6_NTOA(%1$s.`post_ip`) AS `post_ip`'
                         . ', UNIX_TIMESTAMP(%1$s.`post_created`) AS `post_created`'
                         . ', UNIX_TIMESTAMP(%1$s.`post_edited`) AS `post_edited`'
                         . ', UNIX_TIMESTAMP(%1$s.`post_deleted`) AS `post_deleted`';

    private $topic = null;
    private $category = null;
    private $user = null;
    private $userLookedUp = false;

    public function getId(): int {
        return $this->post_id < 1 ? -1 : $this->post_id;
    }

    public function getTopicId(): int {
        return $this->topic_id < 1 ? -1 : $this->topic_id;
    }
    public function setTopicId(int $topicId): self {
        $this->topic_id = $topicId;
        $this->topic = null;
        return $this;
    }
    public function getTopic(): ForumTopic {
        if($this->topic === null)
            $this->topic = ForumTopic::byId($this->getTopicId());
        return $this->topic;
    }
    public function setTopic(ForumTopic $topic): self {
        $this->topic_id = $topic->getId();
        $this->topic = $topic;
        return $this;
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
        $this->userLookedUp = false;
        return $this;
    }
    public function hasUser(): bool {
        return $this->getUserId() > 0;
    }
    public function getUser(): ?User {
        if(!$this->userLookedUp) {
            $this->userLookedUp = true;
            try {
                $this->user = User::byId($this->getUserId());
            } catch(UserNotFoundException $ex) {}
        }
        return $this->user;
    }
    public function setUser(?User $user): self {
        $this->user_id = $user === null ? null : $user->getId();
        $this->user = $user;
        $this->userLookedUp = true;
        return $this;
    }

    public function getRemoteAddress(): string {
        return $this->post_ip;
    }

    public function getBody(): string {
        return $this->post_text;
    }
    public function getParsedBody(): string {
        return Parser::instance($this->getBodyParser())->parseText(htmlspecialchars($this->getBody()));
    }
    public function getFirstBodyParagraph(): string {
        return htmlspecialchars(first_paragraph($this->getBody()));
    }
    public function setBody(string $body): self {
        $this->post_text = empty($body) ? null : $body;
        return $this;
    }

    public function getBodyParser(): int {
        return $this->post_parse;
    }
    public function setBodyParser(int $parser): self {
        $this->post_parse = $parser;
        return $this;
    }

    public function getBodyClasses(): string {
        if($this->getBodyParser() === Parser::MARKDOWN)
            return 'markdown';
        return '';
    }

    public function shouldDisplaySignature(): bool {
        return boolval($this->post_display_signature);
    }
    public function setDisplaySignature(bool $display): self {
        $this->post_display_signature = $display ? 1 : 0;
        return $this;
    }

    public function getCreatedTime(): int {
        return $this->post_created === null ? -1 : $this->post_created;
    }

    public function getEditedTime(): int {
        return $this->post_edited === null ? -1 : $this->post_edited;
    }
    public function isEdited(): bool {
        return $this->getEditedTime() >= 0;
    }
    public function bumpEdited(): self {
        $this->post_edited = time();
        return $this;
    }
    public function stripEdited(): self {
        $this->post_edited = null;
        return $this;
    }

    public function getDeletedTime(): int {
        return $this->post_deleted === null ? -1 : $this->post_deleted;
    }
    public function isDeleted(): bool {
        return $this->getDeletedTime() >= 0;
    }
    public function setDeleted(bool $deleted): self {
        if($this->isDeleted() !== $deleted)
            $this->post_deleted = $deleted ? time() : null;
        return $this;
    }

    public function isOpeningPost(): bool {
        return $this->getTopic()->isOpeningPost($this);
    }

    public function isTopicAuthor(): bool {
        return $this->getTopic()->isTopicAuthor($this->getUser());
    }

    public function canBeSeen(?User $user): bool {
        if($user === null && $this->isDeleted())
            return false;
        // check if user can view deleted posts
        return true;
    }

    // complete this implementation
    public function canBeEdited(?User $user): bool {
        if($user === null)
            return false;
        return $this->getUser()->getId() === $user->getId();
    }

    // complete this implementation
    public function canBeDeleted(?User $user): bool {
        if($user === null)
            return false;
        return $this->getUser()->getId() === $user->getId();
    }

    private static function countQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf('COUNT(*)', self::TABLE));
    }
    public static function countByCategory(ForumCategory $category, bool $includeDeleted = false): int {
        return (int)DB::prepare(
            self::countQueryBase()
            . ' WHERE `forum_id` = :category'
            . ($includeDeleted ? '' : ' AND `post_deleted` IS NULL')
        )->bind('category', $category->getId())->fetchColumn();
    }
    public static function countByTopic(ForumTopic $topic, bool $includeDeleted = false): int {
        return (int)DB::prepare(
            self::countQueryBase()
            . ' WHERE `topic_id` = :topic'
            . ($includeDeleted ? '' : ' AND `post_deleted` IS NULL')
        )->bind('topic', $topic->getId())->fetchColumn();
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
    public static function byId(int $postId): self {
        return self::memoizer()->find($postId, function() use ($postId) {
            $object = DB::prepare(self::byQueryBase() . ' WHERE `post_id` = :post')
                ->bind('post', $postId)
                ->fetchObject(self::class);
            if(!$object)
                throw new ForumPostNotFoundException;
            return $object;
        });
    }
    public static function byTopic(ForumTopic $topic, bool $includeDeleted = false, ?Pagination $pagination = null): array {
        $query = self::byQueryBase()
                . ' WHERE `topic_id` = :topic'
                . ($includeDeleted ? '' : ' AND `post_deleted` IS NULL')
                . ' ORDER BY `post_id`';

        if($pagination !== null)
            $query .= ' LIMIT :range OFFSET :offset';

        $getObjects = DB::prepare($query)
            ->bind('topic', $topic->getId());

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
