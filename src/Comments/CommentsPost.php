<?php
namespace Misuzu\Comments;

use JsonSerializable;
use Misuzu\DB;
use Misuzu\Pagination;
use Misuzu\Users\User;
use Misuzu\Users\UserNotFoundException;

class CommentsPostException extends CommentsException {}
class CommentsPostNotFoundException extends CommentsPostException {}
class CommentsPostHasNoParentException extends CommentsPostException {}
class CommentsPostSaveFailedException extends CommentsPostException {}

class CommentsPost implements JsonSerializable {
    // Database fields
    private $comment_id = -1;
    private $category_id = -1;
    private $user_id = null;
    private $comment_reply_to = null;
    private $comment_text = '';
    private $comment_created = null;
    private $comment_pinned = null;
    private $comment_edited = null;
    private $comment_deleted = null;

    // Virtual fields
    private $comment_likes = -1;
    private $comment_dislikes = -1;
    private $user_vote = null;

    private $category = null;
    private $user = null;
    private $userLookedUp = false;
    private $parentPost = null;

    public const TABLE = 'comments_posts';
    private const QUERY_SELECT = 'SELECT %1$s FROM `' . DB::PREFIX . self::TABLE . '` AS '. self::TABLE;
    private const SELECT = '%1$s.`comment_id`, %1$s.`category_id`, %1$s.`user_id`, %1$s.`comment_reply_to`, %1$s.`comment_text`'
        . ', UNIX_TIMESTAMP(%1$s.`comment_created`) AS `comment_created`'
        . ', UNIX_TIMESTAMP(%1$s.`comment_pinned`) AS `comment_pinned`'
        . ', UNIX_TIMESTAMP(%1$s.`comment_edited`) AS `comment_edited`'
        . ', UNIX_TIMESTAMP(%1$s.`comment_deleted`) AS `comment_deleted`';
    private const LIKE_VOTE_SELECT    = '(SELECT COUNT(`comment_id`) FROM `' . DB::PREFIX . CommentsVote::TABLE . '` WHERE `comment_id` = %1$s.`comment_id` AND `comment_vote` = ' . CommentsVote::LIKE . ') AS `comment_likes`';
    private const DISLIKE_VOTE_SELECT = '(SELECT COUNT(`comment_id`) FROM `' . DB::PREFIX . CommentsVote::TABLE . '` WHERE `comment_id` = %1$s.`comment_id` AND `comment_vote` = ' . CommentsVote::DISLIKE . ') AS `comment_dislikes`';
    private const USER_VOTE_SELECT    = '(SELECT `comment_vote` FROM `' . DB::PREFIX . CommentsVote::TABLE . '` WHERE `comment_id` = %1$s.`comment_id` AND `user_id` = :user) AS `user_vote`';

    public function getId(): int {
        return $this->comment_id < 1 ? -1 : $this->comment_id;
    }

    public function getCategoryId(): int {
        return $this->category_id < 1 ? -1 : $this->category_id;
    }
    public function setCategoryId(int $categoryId): self {
        $this->category_id = $categoryId;
        $this->category = null;
        return $this;
    }
    public function getCategory(): CommentsCategory {
        if($this->category === null)
            $this->category = CommentsCategory::byId($this->getCategoryId());
        return $this->category;
    }
    public function setCategory(CommentsCategory $category): self {
        $this->category_id = $category->getId();
        $this->category = null;
        return $this;
    }

    public function getUserId(): int {
        return $this->user_id < 1 ? -1 : $this->user_id;
    }
    public function setUserId(int $userId): self {
        $this->user_id = $userId < 1 ? null : $userId;
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
        $this->user = $user;
        return $this;
    }

    public function getParentId(): int {
        return $this->comment_reply_to < 1 ? -1 : $this->comment_reply_to;
    }
    public function setParentId(int $parentId): self {
        $this->comment_reply_to = $parentId < 1 ? null : $parentId;
        $this->parentPost = null;
        return $this;
    }
    public function hasParent(): bool {
        return $this->getParentId() > 0;
    }
    public function getParent(): CommentsPost {
        if(!$this->hasParent())
            throw new CommentsPostHasNoParentException;
        if($this->parentPost === null)
            $this->parentPost = CommentsPost::byId($this->getParentId());
        return $this->parentPost;
    }
    public function setParent(?CommentsPost $parent): self {
        $this->comment_reply_to = $parent === null ? null : $parent->getId();
        $this->parentPost = $parent;
        return $this;
    }

    public function getText(): string {
        return $this->comment_text;
    }
    public function setText(string $text): self {
        $this->comment_text = $text;
        return $this;
    }
    public function getParsedText(): string {
        return CommentsParser::parseForDisplay($this->getText());
    }
    public function setParsedText(string $text): self {
        return $this->setText(CommentsParser::parseForStorage($text));
    }

    public function getCreatedTime(): int {
        return $this->comment_created === null ? -1 : $this->comment_created;
    }

    public function getPinnedTime(): int {
        return $this->comment_pinned === null ? -1 : $this->comment_pinned;
    }
    public function isPinned(): bool {
        return $this->getPinnedTime() >= 0;
    }
    public function setPinned(bool $pinned): self {
        if($this->isPinned() !== $pinned)
            $this->comment_pinned = $pinned ? time() : null;
        return $this;
    }

    public function getEditedTime(): int {
        return $this->comment_edited === null ? -1 : $this->comment_edited;
    }
    public function isEdited(): bool {
        return $this->getEditedTime() >= 0;
    }

    public function getDeletedTime(): int {
        return $this->comment_deleted === null ? -1 : $this->comment_deleted;
    }
    public function isDeleted(): bool {
        return $this->getDeletedTime() >= 0;
    }
    public function setDeleted(bool $deleted): self {
        if($this->isDeleted() !== $deleted)
            $this->comment_deleted = $deleted ? time() : null;
        return $this;
    }

    public function getLikes(): int {
        return $this->comment_likes;
    }
    public function getDislikes(): int {
        return $this->comment_dislikes;
    }

    public function hasUserVote(): bool {
        return $this->user_vote !== null;
    }
    public function getUserVote(): int {
        return $this->user_vote ?? 0;
    }

    public function jsonSerialize() {
        $json = [
            'id'       => $this->getId(),
            'category' => $this->getCategoryId(),
            'user'     => $this->getUserId(),
            'parent'   => ($parent = $this->getParentId()) < 1 ? null : $parent,
            'text'     => $this->getText(),
            'created'  => ($created = $this->getCreatedTime()) < 0 ? null : date('c', $created),
            'pinned'   => ($pinned  = $this->getPinnedTime())  < 0 ? null : date('c', $pinned),
            'edited'   => ($edited  = $this->getEditedTime())  < 0 ? null : date('c', $edited),
            'deleted'  => ($deleted = $this->getDeletedTime()) < 0 ? null : date('c', $deleted),
        ];

        if(($likes = $this->getLikes()) >= 0)
            $json['likes'] = $likes;
        if(($dislikes = $this->getDislikes()) >= 0)
            $json['dislikes'] = $dislikes;

        if($this->hasUserVote())
            $json['user_vote'] = $this->getUserVote();

        return $json;
    }

    public function save(): void {
        $isInsert = $this->getId() < 1;
        if($isInsert) {
            $query = 'INSERT INTO `%1$s%2$s` (`category_id`, `user_id`, `comment_reply_to`, `comment_text`'
                . ', `comment_pinned`, `comment_deleted`) VALUES'
                . ' (:category, :user, :parent, :text, FROM_UNIXTIME(:pinned), FROM_UNIXTIME(:deleted))';
        } else {
            $query = 'UPDATE `%1$s%2$s` SET `category_id` = :category, `user_id` = :user, `comment_reply_to` = :parent'
                . ', `comment_text` = :text, `comment_pinned` = FROM_UNIXTIME(:pinned), `comment_deleted` = FROM_UNIXTIME(:deleted)'
                . ' WHERE `comment_id` = :post';
        }

        $savePost = DB::prepare(sprintf($query, DB::PREFIX, self::TABLE))
            ->bind('category', $this->category_id)
            ->bind('user', $this->user_id)
            ->bind('parent', $this->comment_reply_to)
            ->bind('text', $this->comment_text)
            ->bind('pinned', $this->comment_pinned)
            ->bind('deleted', $this->comment_deleted);

        if($isInsert) {
            $this->comment_id = $savePost->executeGetId();
            if($this->comment_id < 1)
                throw new CommentsPostSaveFailedException;
            $this->comment_created = time();
        } else {
            $this->comment_edited = time();
            $savePost->bind('post', $this->getId());
            if(!$savePost->execute())
                throw new CommentsPostSaveFailedException;
        }
    }

    public function nuke(): void {
        $replies = $this->replies(null, true);
        foreach($replies as $reply)
            $reply->nuke();
        DB::prepare('DELETE FROM `' . DB::PREFIX . self::TABLE . '` WHERE `comment_id` = :comment')
            ->bind('comment_id', $this->getId())
            ->execute();
    }

    public function replies(?User $voteUser = null, bool $includeVotes = true, ?Pagination $pagination = null, bool $includeDeleted = true): array {
        return CommentsPost::byParent($this, $voteUser, $includeVotes, $pagination, $includeDeleted);
    }
    public function votes(): CommentsVoteCount {
        return CommentsVote::countByPost($this);
    }
    public function childVotes(?User $user = null, ?Pagination $pagination = null): array {
        return CommentsVote::byParent($this, $user, $pagination);
    }

    public function addPositiveVote(User $user): void {
        CommentsVote::create($this, $user, CommentsVote::LIKE);
    }
    public function addNegativeVote(User $user): void {
        CommentsVote::create($this, $user, CommentsVote::DISLIKE);
    }
    public function removeVote(User $user): void {
        CommentsVote::delete($this, $user);
    }

    public function getVoteFromUser(User $user): CommentsVote {
        return CommentsVote::byExact($this, $user);
    }

    private static function byQueryBase(bool $includeVotes = true, bool $includeUserVote = false): string {
        $select = self::SELECT;
        if($includeVotes)
            $select .= ', ' . self::LIKE_VOTE_SELECT
                    .  ', ' . self::DISLIKE_VOTE_SELECT;
        if($includeUserVote)
            $select .= ', ' . self::USER_VOTE_SELECT;
        return sprintf(self::QUERY_SELECT, sprintf($select, self::TABLE));
    }
    public static function byId(int $postId): self {
        $getPost = DB::prepare(self::byQueryBase() . ' WHERE `comment_id` = :post_id');
        $getPost->bind('post_id', $postId);
        $post = $getPost->fetchObject(self::class);
        if(!$post)
            throw new CommentsPostNotFoundException;
        return $post;
    }
    public static function byCategory(CommentsCategory $category, ?User $voteUser = null, bool $includeVotes = true, ?Pagination $pagination = null, bool $rootOnly = true, bool $includeDeleted = true): array {
        $postsQuery = self::byQueryBase($includeVotes, $voteUser !== null)
            . ' WHERE `category_id` = :category'
            . (!$rootOnly      ? '' : ' AND `comment_reply_to` IS NULL')
            . ($includeDeleted ? '' : ' AND `comment_deleted`  IS NULL')
            . ' ORDER BY `comment_deleted` ASC, `comment_pinned` DESC, `comment_id` DESC';

        if($pagination !== null)
            $postsQuery .= ' LIMIT :range OFFSET :offset';

        $getPosts = DB::prepare($postsQuery)
            ->bind('category', $category->getId());

        if($voteUser !== null)
            $getPosts->bind('user', $voteUser->getId());

        if($pagination !== null)
            $getPosts->bind('range', $pagination->getRange())
                ->bind('offset', $pagination->getOffset());

        return $getPosts->fetchObjects(self::class);
    }
    public static function byParent(CommentsPost $parent, ?User $voteUser = null, bool $includeVotes = true, ?Pagination $pagination = null, bool $includeDeleted = true): array {
        $postsQuery = self::byQueryBase($includeVotes, $voteUser !== null)
            . ' WHERE `comment_reply_to` = :parent'
            . ($includeDeleted ? '' : ' AND `comment_deleted` IS NULL')
            . ' ORDER BY `comment_deleted` ASC, `comment_pinned` DESC, `comment_id` ASC';

        if($pagination !== null)
            $postsQuery .= ' LIMIT :range OFFSET :offset';

        $getPosts = DB::prepare($postsQuery)
            ->bind('parent', $parent->getId());

        if($voteUser !== null)
            $getPosts->bind('user', $voteUser->getId());

        if($pagination !== null)
            $getPosts->bind('range', $pagination->getRange())
                ->bind('offset', $pagination->getOffset());

        return $getPosts->fetchObjects(self::class);
    }
    public static function all(?Pagination $pagination = null, bool $rootOnly = true, bool $includeDeleted = false): array {
        $postsQuery = self::byQueryBase()
            . ' WHERE 1' // this is disgusting
            . (!$rootOnly      ? '' : ' AND `comment_reply_to` IS NULL')
            . ($includeDeleted ? '' : ' AND `comment_deleted`  IS NULL')
            . ' ORDER BY `comment_id` DESC';

        if($pagination !== null)
            $postsQuery .= ' LIMIT :range OFFSET :offset';

        $getPosts = DB::prepare($postsQuery);

        if($pagination !== null)
            $getPosts->bind('range', $pagination->getRange())
                ->bind('offset', $pagination->getOffset());

        return $getPosts->fetchObjects(self::class);
    }
}
