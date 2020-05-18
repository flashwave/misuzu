<?php
namespace Misuzu\Comments;

use JsonSerializable;
use Misuzu\DB;
use Misuzu\Pagination;
use Misuzu\Users\User;

class CommentsVoteException extends CommentsException {}
class CommentsVoteCountFailedException extends CommentsVoteException {}
class CommentsVoteCreateFailedException extends CommentsVoteException {}

class CommentsVoteCount implements JsonSerializable {
    private $comment_id = -1;
    private $likes = 0;
    private $dislikes = 0;
    private $total = 0;

    public function getPostId(): int {
        return $this->comment_id < 1 ? -1 : $this->comment_id;
    }
    public function getLikes(): int {
        return $this->likes;
    }
    public function getDislikes(): int {
        return $this->dislikes;
    }
    public function getTotal(): int {
        return $this->total;
    }

    public function jsonSerialize() {
        return [
            'id'       => $this->getPostId(),
            'likes'    => $this->getLikes(),
            'dislikes' => $this->getDislikes(),
            'total'    => $this->getTotal(),
        ];
    }
}

class CommentsVote implements JsonSerializable {
    // Database fields
    private $comment_id = -1;
    private $user_id = -1;
    private $comment_vote = 0;

    private $comment = null;
    private $user = null;

    public const LIKE    =  1;
    public const NONE    =  0;
    public const DISLIKE = -1;

    public const TABLE = 'comments_votes';
    private const QUERY_SELECT = 'SELECT %1$s FROM `' . DB::PREFIX . self::TABLE . '` AS '. self::TABLE;
    private const SELECT = '%1$s.`comment_id`, %1$s.`user_id`, %1$s.`comment_vote`';

    private const QUERY_COUNT = 'SELECT %3$d AS `comment_id`'
        . ', (SELECT COUNT(`comment_id`) FROM `%1$s%2$s` WHERE %6$s) AS `total`'
        . ', (SELECT COUNT(`comment_id`) FROM `%1$s%2$s` WHERE %6$s AND `comment_vote` = %4$d) AS `likes`'
        . ', (SELECT COUNT(`comment_id`) FROM `%1$s%2$s` WHERE %6$s AND `comment_vote` = %5$d) AS `dislikes`';

    public function getPostId(): int {
        return $this->comment_id < 1 ? -1 : $this->comment_id;
    }
    public function getPost(): CommentsPost {
        if($this->comment === null)
            $this->comment = CommentsPost::byId($this->comment_id);
        return $this->comment;
    }

    public function getUserId(): int {
        return $this->user_id < 1 ? -1 : $this->user_id;
    }
    public function getUser(): User {
        if($this->user === null)
            $this->user = User::byId($this->user_id);
        return $this->user;
    }

    public function getVote(): int {
        return $this->comment_vote;
    }

    public function jsonSerialize() {
        return [
            'post' => $this->getPostId(),
            'user' => $this->getUserId(),
            'vote' => $this->getVote(),
        ];
    }

    public static function create(CommentsPost $post, User $user, int $vote, bool $return = false): ?self {
        $createVote = DB::prepare('
            REPLACE INTO `msz_comments_votes`
                (`comment_id`, `user_id`, `comment_vote`)
            VALUES
                (:post, :user, :vote)
        ')  ->bind('post', $post->getId())
            ->bind('user', $user->getId())
            ->bind('vote', $vote);

        if(!$createVote->execute())
            throw new CommentsVoteCreateFailedException;
        if(!$return)
            return null;

        return CommentsVote::byExact($post, $user);
    }

    public static function delete(CommentsPost $post, User $user): void {
        DB::prepare('DELETE FROM `msz_comments_votes` WHERE `comment_id` = :post AND `user_id` = :user')
            ->bind('post', $post->getId())
            ->bind('user', $user->getId())
            ->execute();
    }

    private static function countQueryBase(int $id, string $condition = '1'): string {
        return sprintf(self::QUERY_COUNT, DB::PREFIX, self::TABLE, $id, self::LIKE, self::DISLIKE, $condition);
    }
    public static function countByPost(CommentsPost $post): CommentsVoteCount {
        $count = DB::prepare(self::countQueryBase($post->getId(), sprintf('`comment_id` = %d', $post->getId())))
            ->fetchObject(CommentsVoteCount::class);
        if(!$count)
            throw new CommentsVoteCountFailedException;
        return $count;
    }

    private static function fake(CommentsPost $post, User $user, int $vote): CommentsVote {
        $fake = new static;
        $fake->comment_id = $post->getId();
        $fake->comment = $post;
        $fake->user_id = $user->getId();
        $fake->user = $user;
        $fake->comment_vote = $vote;
        return $fake;
    }

    private static function byQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf(self::SELECT, self::TABLE));
    }
    public static function byExact(CommentsPost $post, User $user): self {
        $vote = DB::prepare(self::byQueryBase() . ' WHERE `comment_id` = :post_id AND `user_id` = :user_id')
            ->bind('post_id', $post->getId())
            ->bind('user_id', $user->getId())
            ->fetchObject(self::class);
        if(!$vote)
            return self::fake($post, $user, self::NONE);
        return $vote;
    }
    public static function byPost(CommentsPost $post, ?User $user = null, ?Pagination $pagination = null): array {
        $votesQuery = self::byQueryBase()
            . ' WHERE `comment_id` = :post'
            . ($user === null ? '' : ' AND `user_id` = :user');

        if($pagination !== null)
            $votesQuery .= ' LIMIT :range OFFSET :offset';

        $getVotes = DB::prepare($votesQuery)
            ->bind('post', $post->getId());

        if($user !== null)
            $getVotes->bind('user', $user->getId());

        if($pagination !== null)
            $getVotes->bind('range', $pagination->getRange())
                ->bind('offset', $pagination->getOffset());

        return $getVotes->fetchObjects(self::class);
    }
    public static function byUser(User $user, ?Pagination $pagination = null): array {
        $votesQuery = self::byQueryBase()
            . ' WHERE `user_id` = :user';

        if($pagination !== null)
            $votesQuery .= ' LIMIT :range OFFSET :offset';

        $getVotes = DB::prepare($votesQuery)
            ->bind('user', $user->getId());

        if($pagination !== null)
            $getVotes->bind('range', $pagination->getRange())
                ->bind('offset', $pagination->getOffset());

        return $getVotes->fetchObjects(self::class);
    }
    public static function byCategory(CommentsCategory $category, ?User $user = null, bool $rootOnly = true, ?Pagination $pagination = null): array {
        $votesQuery = self::byQueryBase()
            . ' WHERE `comment_id` IN'
            . ' (SELECT `comment_id` FROM `' . DB::PREFIX . CommentsPost::TABLE . '` WHERE `category_id` = :category'
            . (!$rootOnly ? '' : ' AND `comment_reply_to` IS NULL')
            . ')'
            . ($user === null ? '' : ' AND `user_id` = :user');

        if($pagination !== null)
            $votesQuery .= ' LIMIT :range OFFSET :offset';

        $getVotes = DB::prepare($votesQuery)
            ->bind('category', $category->getId());

        if($user !== null)
            $getVotes->bind('user', $user->getId());

        if($pagination !== null)
            $getVotes->bind('range', $pagination->getRange())
                ->bind('offset', $pagination->getOffset());

        return $getVotes->fetchObjects(self::class);
    }
    public static function byParent(CommentsPost $parent, ?User $user = null, ?Pagination $pagination = null): array {
        $votesQuery = self::byQueryBase()
            . ' WHERE `comment_id` IN'
            . ' (SELECT `comment_id` FROM `' . DB::PREFIX . CommentsPost::TABLE . '` WHERE `comment_reply_to` = :parent)'
            . ($user === null ? '' : ' AND `user_id` = :user');

        if($pagination !== null)
            $votesQuery .= ' LIMIT :range OFFSET :offset';

        $getVotes = DB::prepare($votesQuery)
            ->bind('parent', $parent->getId());

        if($user !== null)
            $getVotes->bind('user', $user->getId());

        if($pagination !== null)
            $getVotes->bind('range', $pagination->getRange())
                ->bind('offset', $pagination->getOffset());

        return $getVotes->fetchObjects(self::class);
    }
    public static function all(?Pagination $pagination = null): array {
        $votesQuery = self::byQueryBase();

        if($pagination !== null)
            $votesQuery .= ' LIMIT :range OFFSET :offset';

        $getVotes = DB::prepare($votesQuery);

        if($pagination !== null)
            $getVotes->bind('range', $pagination->getRange())
                ->bind('offset', $pagination->getOffset());

        return $getVotes->fetchObjects(self::class);
    }
}
