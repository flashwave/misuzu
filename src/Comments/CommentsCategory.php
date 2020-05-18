<?php
namespace Misuzu\Comments;

use JsonSerializable;
use Misuzu\DB;
use Misuzu\Memoizer;
use Misuzu\Pagination;
use Misuzu\Users\User;

class CommentsCategoryException extends CommentsException {};
class CommentsCategoryNotFoundException extends CommentsCategoryException {};

class CommentsCategory implements JsonSerializable {
    // Database fields
    private $category_id = -1;
    private $category_name = '';
    private $category_created = null;
    private $category_locked = null;

    private $postCount = -1;

    public const TABLE = 'comments_categories';
    private const QUERY_SELECT = 'SELECT %1$s FROM `' . DB::PREFIX . self::TABLE . '` AS '. self::TABLE;
    private const SELECT = '%1$s.`category_id`, %1$s.`category_name`'
        . ', UNIX_TIMESTAMP(%1$s.`category_created`) AS `category_created`'
        . ', UNIX_TIMESTAMP(%1$s.`category_locked`) AS `category_locked`';

    public function __construct(?string $name = null) {
        if($name !== null)
            $this->setName($name);
    }

    public function getId(): int {
        return $this->category_id < 1 ? -1 : $this->category_id;
    }

    public function getName(): string {
        return $this->category_name;
    }
    public function setName(string $name): self {
        $this->category_name = $name;
        return $this;
    }

    public function getCreatedTime(): int {
        return $this->category_created === null ? -1 : $this->category_created;
    }

    public function getLockedTime(): int {
        return $this->category_locked === null ? -1 : $this->category_locked;
    }
    public function isLocked(): bool {
        return $this->getLockedTime() >= 0;
    }
    public function setLocked(bool $locked): self {
        if($locked !== $this->isLocked())
            $this->category_locked = $locked ? time() : null;
        return $this;
    }

    // Purely cosmetic, do not use for anything other than displaying
    public function getPostCount(): int {
        if($this->postCount < 0)
            $this->postCount = (int)DB::prepare('
                SELECT COUNT(`comment_id`)
                FROM `msz_comments_posts`
                WHERE `category_id` = :cat_id
                AND `comment_deleted` IS NULL
            ')->bind('cat_id', $this->getId())->fetchColumn();

        return $this->postCount;
    }

    public function jsonSerialize() {
        return [
            'id'      => $this->getId(),
            'name'    => $this->getName(),
            'created' => ($created = $this->getCreatedTime()) < 0 ? null : date('c', $created),
            'locked'  => ($locked  = $this->getLockedTime())  < 0 ? null : date('c', $locked),
        ];
    }

    public function save(): void {
        $isInsert = $this->getId() < 1;
        if($isInsert) {
            $query = 'INSERT INTO `%1$s%2$s` (`category_name`, `category_locked`) VALUES'
                . ' (:name, :locked)';
        } else {
            $query = 'UPDATE `%1$s%2$s` SET `category_name` = :name, `category_locked` = FROM_UNIXTIME(:locked)'
                . ' WHERE `category_id` = :category';
        }

        $saveCategory = DB::prepare(sprintf($query, DB::PREFIX, self::TABLE))
            ->bind('name', $this->category_name)
            ->bind('locked', $this->category_locked);

        if($isInsert) {
            $this->category_id = $saveCategory->executeGetId();
            $this->category_created = time();
        } else {
            $saveCategory->bind('category', $this->getId())
                ->execute();
        }
    }

    public function posts(?User $voteUser = null, bool $includeVotes = true, ?Pagination $pagination = null, bool $rootOnly = true, bool $includeDeleted = true): array {
        return CommentsPost::byCategory($this, $voteUser, $includeVotes, $pagination, $rootOnly, $includeDeleted);
    }
    public function votes(?User $user = null, bool $rootOnly = true, ?Pagination $pagination = null): array {
        return CommentsVote::byCategory($this, $user, $rootOnly, $pagination);
    }

    private static function getMemoizer() {
        static $memoizer = null;
        if($memoizer === null)
            $memoizer = new Memoizer;
        return $memoizer;
    }

    private static function byQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf(self::SELECT, self::TABLE));
    }
    public static function byId(int $categoryId): self {
        return self::getMemoizer()->find($categoryId, function() use ($categoryId) {
            $cat = DB::prepare(self::byQueryBase() . ' WHERE `category_id` = :cat_id')
                ->bind('cat_id', $categoryId)
                ->fetchObject(self::class);
            if(!$cat)
                throw new CommentsCategoryNotFoundException;
            return $cat;
        });
    }
    public static function byName(string $categoryName): self {
        return self::getMemoizer()->find(function($category) use ($categoryName) {
            return $category->getName() === $categoryName;
        }, function() use ($categoryName) {
            $cat = DB::prepare(self::byQueryBase() . ' WHERE `category_name` = :name')
                ->bind('name', $categoryName)
                ->fetchObject(self::class);
            if(!$cat)
                throw new CommentsCategoryNotFoundException;
            return $cat;
        });
    }
    public static function all(?Pagination $pagination = null): array {
        $catsQuery = self::byQueryBase()
            . ' ORDER BY `category_id` ASC';

        if($pagination !== null)
            $catsQuery .= ' LIMIT :range OFFSET :offset';

        $getCats = DB::prepare($catsQuery);

        if($pagination !== null)
            $getCats->bind('range', $pagination->getRange())
                ->bind('offset', $pagination->getOffset());

        return $getCats->fetchObjects(self::class);
    }
}