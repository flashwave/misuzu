<?php
namespace Misuzu\News;

use ArrayAccess;
use JsonSerializable;
use Misuzu\DB;
use Misuzu\Pagination;

class NewsCategoryException extends NewsException {};
class NewsCategoryNotFoundException extends NewsCategoryException {};

class NewsCategory implements ArrayAccess, JsonSerializable {
    // Database fields
    private $category_id = -1;
    private $category_name = '';
    private $category_description = '';
    private $category_is_hidden = false;
    private $category_created = null;

    private $postCount = -1;

    public const TABLE = 'news_categories';
    private const QUERY_SELECT = 'SELECT %1$s FROM `' . DB::PREFIX . self::TABLE . '` AS '. self::TABLE;
    private const SELECT = '%1$s.`category_id`, %1$s.`category_name`, %1$s.`category_description`, %1$s.`category_is_hidden`'
        . ', UNIX_TIMESTAMP(%1$s.`category_created`) AS `category_created`';

    public function __construct() {}

    public function getId(): int {
        return $this->category_id < 1 ? -1 : $this->category_id;
    }

    public function getName(): string {
        return $this->category_name ?? '';
    }
    public function setName(string $name): self {
        $this->category_name = $name;
        return $this;
    }

    public function getDescription(): string {
        return $this->category_description ?? '';
    }
    public function setDescription(string $description): self {
        $this->category_description = $description;
        return $this;
    }

    public function isHidden(): bool {
        return $this->category_is_hidden !== 0;
    }
    public function setHidden(bool $hide): self {
        $this->category_is_hidden = $hide ? 1 : 0;
        return $this;
    }

    public function getCreatedTime(): int {
        return $this->category_created === null ? -1 : $this->category_created;
    }

    public function jsonSerialize() {
        return [
            'id'          => $this->getId(),
            'name'        => $this->getName(),
            'description' => $this->getDescription(),
            'is_hidden'   => $this->isHidden(),
            'created'     => ($time = $this->getCreatedTime()) < 0 ? null : date('c', $time),
        ];
    }

    // Purely cosmetic, use ::countAll for pagination
    public function getPostCount(): int {
        if($this->postCount < 0)
            $this->postCount = (int)DB::prepare('
                SELECT COUNT(`post_id`)
                FROM `msz_news_posts`
                WHERE `category_id` = :cat_id
                AND `post_scheduled` <= NOW()
                AND `post_deleted` IS NULL
            ')->bind('cat_id', $this->getId())->fetchColumn();

        return $this->postCount;
    }

    public function save(): void {
        $isInsert = $this->getId() < 1;
        if($isInsert) {
            $query = 'INSERT INTO `%1$s%2$s` (`category_name`, `category_description`, `category_is_hidden`) VALUES'
                . ' (:name, :description, :hidden)';
        } else {
            $query = 'UPDATE `%1$s%2$s` SET `category_name` = :name, `category_description` = :description, `category_is_hidden` = :hidden'
                . ' WHERE `category_id` = :category';
        }

        $savePost = DB::prepare(sprintf($query, DB::PREFIX, self::TABLE))
            ->bind('name', $this->category_name)
            ->bind('description', $this->category_description)
            ->bind('hidden', $this->category_is_hidden);

        if($isInsert) {
            $this->category_id = $savePost->executeGetId();
            $this->category_created = time();
        } else {
            $savePost->bind('category', $this->getId())
                ->execute();
        }
    }

    public function posts(?Pagination $pagination = null, bool $includeScheduled = false, bool $includeDeleted = false): array {
        return NewsPost::byCategory($this, $pagination, $includeScheduled, $includeDeleted);
    }

    private static function countQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf('COUNT(%s.`category_id`)', self::TABLE));
    }
    public static function countAll(bool $showHidden = false): int {
        return (int)DB::prepare(self::countQueryBase()
            . ($showHidden ? '' : ' WHERE `category_is_hidden` = 0'))
            ->fetchColumn();
    }

    private static function byQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf(self::SELECT, self::TABLE));
    }
    public static function byId(int $categoryId): self {
        $getCat = DB::prepare(self::byQueryBase() . ' WHERE `category_id` = :cat_id');
        $getCat->bind('cat_id', $categoryId);
        $cat = $getCat->fetchObject(self::class);
        if(!$cat)
            throw new NewsCategoryNotFoundException;
        return $cat;
    }
    public static function all(?Pagination $pagination = null, bool $showHidden = false): array {
        $catsQuery = self::byQueryBase()
            . ($showHidden ? '' : ' WHERE `category_is_hidden` = 0')
            . ' ORDER BY `category_id` ASC';

        if($pagination !== null)
            $catsQuery .= ' LIMIT :range OFFSET :offset';

        $getCats = DB::prepare($catsQuery);

        if($pagination !== null)
            $getCats->bind('range', $pagination->getRange())
                ->bind('offset', $pagination->getOffset());

        return $getCats->fetchObjects(self::class);
    }

    // Twig shim for the news category list in manage, don't use this class as an array normally.
    public function offsetExists($offset): bool {
        return $offset === 'name' || $offset === 'id';
    }
    public function offsetGet($offset) {
        return $this->{'get' . ucfirst($offset)}();
    }
    public function offsetSet($offset, $value) {}
    public function offsetUnset($offset) {}
}
