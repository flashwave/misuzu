<?php
namespace Misuzu\News;

use JsonSerializable;
use Misuzu\DB;
use Misuzu\Pagination;
use Misuzu\Comments\CommentsCategory;
use Misuzu\Comments\CommentsCategoryNotFoundException;
use Misuzu\Parsers\Parser;
use Misuzu\Users\User;
use Misuzu\Users\UserNotFoundException;

class NewsPostException extends NewsException {};
class NewsPostNotFoundException extends NewsPostException {};

class NewsPost implements JsonSerializable {
    // Database fields
    private $post_id = -1;
    private $category_id = -1;
    private $user_id = null;
    private $comment_section_id = null;
    private $post_is_featured = false;
    private $post_title = '';
    private $post_text = '';
    private $post_scheduled = null;
    private $post_created = null;
    private $post_updated = null;
    private $post_deleted = null;

    private $category = null;
    private $user = null;
    private $userLookedUp = false;
    private $comments = null;

    public const TABLE = 'news_posts';
    private const QUERY_SELECT = 'SELECT %1$s FROM `' . DB::PREFIX . self::TABLE . '` AS '. self::TABLE;
    private const SELECT = '%1$s.`post_id`, %1$s.`category_id`, %1$s.`user_id`, %1$s.`comment_section_id`'
        . ', %1$s.`post_is_featured`, %1$s.`post_title`, %1$s.`post_text`'
        . ', UNIX_TIMESTAMP(%1$s.`post_scheduled`) AS `post_scheduled`'
        . ', UNIX_TIMESTAMP(%1$s.`post_created`) AS `post_created`'
        . ', UNIX_TIMESTAMP(%1$s.`post_updated`) AS `post_updated`'
        . ', UNIX_TIMESTAMP(%1$s.`post_deleted`) AS `post_deleted`';

    public function __construct() {}

    public function getId(): int {
        return $this->post_id < 1 ? -1 : $this->post_id;
    }

    public function getCategoryId(): int {
        return $this->category_id < 1 ? -1 : $this->category_id;
    }
    public function setCategoryId(int $categoryId): self {
        $this->category_id = max(1, $categoryId);
        $this->category = null;
        return $this;
    }
    public function getCategory(): NewsCategory {
        if($this->category === null)
            $this->category = NewsCategory::byId($this->getCategoryId());
        return $this->category;
    }
    public function setCategory(NewsCategory $category): self {
        $this->category_id = $category->getId();
        $this->category = $category;
        return $this;
    }

    public function getUserId(): int {
        return $this->user_id < 1 ? -1 : $this->user_id;
    }
    public function setUserId(int $userId): self {
        $this->user_id = $userId < 1 ? null : $userId;
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

    public function getCommentsCategoryId(): int {
        return $this->comment_section_id < 1 ? -1 : $this->comment_section_id;
    }
    public function hasCommentsCategory(): bool {
        return $this->getCommentsCategoryId() > 0;
    }
    public function getCommentsCategory(): CommentsCategory {
        if($this->comments === null)
            $this->comments = CommentsCategory::byId($this->getCommentsCategoryId());
        return $this->comments;
    }

    public function isFeatured(): bool {
        return $this->post_is_featured !== 0;
    }
    public function setFeatured(bool $featured): self {
        $this->post_is_featured = $featured ? 1 : 0;
        return $this;
    }

    public function getTitle(): string {
        return $this->post_title;
    }
    public function setTitle(string $title): self {
        $this->post_title = $title;
        return $this;
    }

    public function getText(): string {
        return $this->post_text;
    }
    public function setText(string $text): self {
        $this->post_text = $text;
        return $this;
    }
    public function getParsedText(): string {
        return Parser::instance(Parser::MARKDOWN)->parseText($this->getText());
    }
    public function getFirstParagraph(): string {
        return first_paragraph($this->getText());
    }
    public function getParsedFirstParagraph(): string {
        return Parser::instance(Parser::MARKDOWN)->parseText($this->getFirstParagraph());
    }

    public function getScheduledTime(): int {
        return $this->post_scheduled === null ? -1 : $this->post_scheduled;
    }
    public function setScheduledTime(int $scheduled): self {
        $time = ($time = $this->getCreatedTime()) < 0 ? time() : $time;
        $this->post_scheduled = $scheduled < $time ? $time : $scheduled;
        return $this;
    }
    public function isPublished(): bool {
        return $this->getScheduledTime() < time();
    }

    public function getCreatedTime(): int {
        return $this->post_created === null ? -1 : $this->post_created;
    }

    public function getUpdatedTime(): int {
        return $this->post_updated === null ? -1 : $this->post_updated;
    }
    public function isEdited(): bool {
        return $this->getUpdatedTime() >= 0;
    }

    public function getDeletedTime(): int {
        return $this->post_deleted === null ? -1 : $this->post_deleted;
    }
    public function isDeleted(): bool {
        return $this->getDeletedTime() >= 0;
    }
    public function setDeleted(bool $isDeleted): self {
        if($this->isDeleted() !== $isDeleted)
            $this->post_deleted = $isDeleted ? time() : null;
        return $this;
    }

    public function jsonSerialize() {
        return [
            'id'          => $this->getId(),
            'category'    => $this->getCategoryId(),
            'user'        => $this->getUserId(),
            'comments'    => $this->getCommentsCategoryId(),
            'is_featured' => $this->isFeatured(),
            'title'       => $this->getTitle(),
            'text'        => $this->getText(),
            'scheduled'   => ($time = $this->getScheduledTime()) < 0 ? null : date('c', $time),
            'created'     => ($time = $this->getCreatedTime())   < 0 ? null : date('c', $time),
            'updated'     => ($time = $this->getUpdatedTime())   < 0 ? null : date('c', $time),
            'deleted'     => ($time = $this->getDeletedTime())   < 0 ? null : date('c', $time),
        ];
    }

    public function ensureCommentsCategory(): void {
        if($this->hasCommentsCategory())
            return;

        $this->comments = new CommentsCategory("news-{$this->getId()}");
        $this->comments->save();

        $this->comment_section_id = $this->comments->getId();
        DB::prepare('UPDATE `msz_news_posts` SET `comment_section_id` = :comment_section_id WHERE `post_id` = :post_id')
            ->execute([
                'comment_section_id' => $this->getCommentsCategoryId(),
                'post_id' => $this->getId(),
            ]);
    }

    public function save(): void {
        $isInsert = $this->getId() < 1;
        if($isInsert) {
            $query = 'INSERT INTO `%1$s%2$s` (`category_id`, `user_id`, `post_is_featured`, `post_title`'
                . ', `post_text`, `post_scheduled`, `post_deleted`) VALUES'
                . ' (:category, :user, :featured, :title, :text, FROM_UNIXTIME(:scheduled), FROM_UNIXTIME(:deleted))';
        } else {
            $query = 'UPDATE `%1$s%2$s` SET `category_id` = :category, `user_id` = :user, `post_is_featured` = :featured'
                . ', `post_title` = :title, `post_text` = :text, `post_scheduled` = FROM_UNIXTIME(:scheduled)'
                . ', `post_deleted` = FROM_UNIXTIME(:deleted)'
                . ' WHERE `post_id` = :post';
        }

        $savePost = DB::prepare(sprintf($query, DB::PREFIX, self::TABLE))
            ->bind('category', $this->category_id)
            ->bind('user', $this->user_id)
            ->bind('featured', $this->post_is_featured)
            ->bind('title', $this->post_title)
            ->bind('text', $this->post_text)
            ->bind('scheduled', $this->post_scheduled)
            ->bind('deleted', $this->post_deleted);

        if($isInsert) {
            $this->post_id = $savePost->executeGetId();
            $this->post_created = time();
        } else {
            $this->post_updated = time();
            $savePost->bind('post', $this->getId())
                ->execute();
        }
    }

    private static function countQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf('COUNT(%s.`post_id`)', self::TABLE));
    }
    public static function countAll(bool $onlyFeatured = false, bool $includeScheduled = false, bool $includeDeleted = false): int {
        return (int)DB::prepare(self::countQueryBase()
            . ' WHERE IF(:only_featured, `post_is_featured` <> 0, 1)'
            . ($includeScheduled ? '' : ' AND `post_scheduled` < NOW()')
            . ($includeDeleted   ? '' : ' AND `post_deleted` IS NULL'))
            ->bind('only_featured', $onlyFeatured ? 1 : 0)
            ->fetchColumn();
    }
    public static function countByCategory(NewsCategory $category, bool $includeScheduled = false, bool $includeDeleted = false): int {
        return (int)DB::prepare(self::countQueryBase()
            . ' WHERE `category_id` = :cat_id'
            . ($includeScheduled ? '' : ' AND `post_scheduled` < NOW()')
            . ($includeDeleted   ? '' : ' AND `post_deleted` IS NULL'))
            ->bind('cat_id', $category->getId())
            ->fetchColumn();
    }

    private static function byQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf(self::SELECT, self::TABLE));
    }
    public static function byId(int $postId): self {
        $post = DB::prepare(self::byQueryBase() . ' WHERE `post_id` = :post_id')
            ->bind('post_id', $postId)
            ->fetchObject(self::class);
        if(!$post)
            throw new NewsPostNotFoundException;
        return $post;
    }
    public static function bySearchQuery(string $query, bool $includeScheduled = false, bool $includeDeleted = false): array {
        return DB::prepare(
            self::byQueryBase()
            . ' WHERE MATCH(`post_title`, `post_text`) AGAINST (:query IN NATURAL LANGUAGE MODE)'
            . ($includeScheduled ? '' : ' AND `post_scheduled` < NOW()')
            . ($includeDeleted   ? '' : ' AND `post_deleted` IS NULL')
            . ' ORDER BY `post_id` DESC'
        )   ->bind('query', $query)
            ->fetchObjects(self::class);
    }
    public static function byCategory(NewsCategory $category, ?Pagination $pagination = null, bool $includeScheduled = false, bool $includeDeleted = false): array {
        $postsQuery = self::byQueryBase()
            . ' WHERE `category_id` = :cat_id'
            . ($includeScheduled ? '' : ' AND `post_scheduled` < NOW()')
            . ($includeDeleted   ? '' : ' AND `post_deleted` IS NULL')
            . ' ORDER BY `post_id` DESC';

        if($pagination !== null)
            $postsQuery .= ' LIMIT :range OFFSET :offset';

        $getPosts = DB::prepare($postsQuery)
            ->bind('cat_id', $category->getId());

        if($pagination !== null)
            $getPosts->bind('range', $pagination->getRange())
                ->bind('offset', $pagination->getOffset());

        return $getPosts->fetchObjects(self::class);
    }
    public static function all(?Pagination $pagination = null, bool $onlyFeatured = false, bool $includeScheduled = false, bool $includeDeleted = false): array {
        $postsQuery = self::byQueryBase()
            . ' WHERE IF(:only_featured, `post_is_featured` <> 0, 1)'
            . ($includeScheduled ? '' : ' AND `post_scheduled` < NOW()')
            . ($includeDeleted   ? '' : ' AND `post_deleted` IS NULL')
            . ' ORDER BY `post_id` DESC';

        if($pagination !== null)
            $postsQuery .= ' LIMIT :range OFFSET :offset';

        $getPosts = DB::prepare($postsQuery)
            ->bind('only_featured', $onlyFeatured ? 1 : 0);

        if($pagination !== null)
            $getPosts->bind('range', $pagination->getRange())
                ->bind('offset', $pagination->getOffset());

        return $getPosts->fetchObjects(self::class);
    }
}
