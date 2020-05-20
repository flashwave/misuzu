<?php
namespace Misuzu\Changelog;

use JsonSerializable;
use Misuzu\DB;
use Misuzu\Memoizer;

class ChangelogTagException extends ChangelogException {}
class ChangelogTagNotFoundException extends ChangelogTagException {}

class ChangelogTag implements JsonSerializable {
    // Database fields
    private $tag_id = -1;
    private $tag_name = '';
    private $tag_description = '';
    private $tag_created = null;
    private $tag_archived = null;

    private $changeCount = -1;

    public const TABLE = 'changelog_tags';
    private const QUERY_SELECT = 'SELECT %1$s FROM `' . DB::PREFIX . self::TABLE . '` AS '. self::TABLE;
    private const SELECT = '%1$s.`tag_id`, %1$s.`tag_name`, %1$s.`tag_description`'
        . ', UNIX_TIMESTAMP(%1$s.`tag_created`) AS `tag_created`'
        . ', UNIX_TIMESTAMP(%1$s.`tag_archived`) AS `tag_archived`';

    public function getId(): int {
        return $this->tag_id < 1 ? -1 : $this->tag_id;
    }

    public function getName(): string {
        return $this->tag_name;
    }
    public function setName(string $name): self {
        $this->tag_name = $name;
        return $this;
    }

    public function getDescription(): string {
        return $this->tag_description;
    }
    public function hasDescription(): bool {
        return !empty($this->tag_description);
    }
    public function setDescription(string $description): self {
        $this->tag_description = $description;
        return $this;
    }

    public function getCreatedTime(): int {
        return $this->tag_created ?? -1;
    }

    public function getArchivedTime(): int {
        return $this->tag_archived ?? -1;
    }
    public function isArchived(): bool {
        return $this->getArchivedTime() >= 0;
    }
    public function setArchived(bool $archived): self {
        if($this->isArchived() !== $archived)
            $this->tag_archived = $archived ? time() : null;
        return $this;
    }

    public function getChangeCount(): int {
        if($this->changeCount < 0)
            $this->changeCount = ChangelogChangeTag::countByTag($this);
        return $this->changeCount;
    }

    public function jsonSerialize() {
        return [
            'id'          => $this->getId(),
            'name'        => $this->getName(),
            'description' => $this->getDescription(),
            'created'     => ($time = $this->getCreatedTime())  < 0 ? null : date('c', $time),
            'archived'    => ($time = $this->getArchivedTime()) < 0 ? null : date('c', $time),
        ];
    }

    public function compare(ChangelogTag $other): bool {
        return $other === $this || $other->getId() === $this->getId();
    }

    public function save(): void {
        $isInsert = $this->getId() < 1;
        if($isInsert) {
            $query = 'INSERT INTO `%1$s%2$s` (`tag_name`, `tag_description`, `tag_archived`)'
                . ' VALUES (:name, :description, FROM_UNIXTIME(:archived))';
        } else {
            $query = 'UPDATE `%1$s%2$s` SET `tag_name` = :name, `tag_description` = :description, `tag_archived` = FROM_UNIXTIME(:archived)'
                . ' WHERE `tag_id` = :tag';
        }

        $saveTag = DB::prepare(sprintf($query, DB::PREFIX, self::TABLE))
            ->bind('name', $this->tag_name)
            ->bind('description', $this->tag_description)
            ->bind('archived', $this->tag_archived);

        if($isInsert) {
            $this->tag_id = $saveTag->executeGetId();
            $this->tag_created = time();
        } else {
            $saveTag->bind('tag', $this->getId())
                ->execute();
        }
    }

    private static function memoizer(): Memoizer {
        static $memoizer = null;
        if($memoizer === null)
            $memoizer = new Memoizer;
        return $memoizer;
    }

    private static function byQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf(self::SELECT, self::TABLE));
    }
    public static function byId(int $tagId): self {
        return self::memoizer()->find($tagId, function() use ($tagId) {
            $tag = DB::prepare(self::byQueryBase() . ' WHERE `tag_id` = :tag')
                ->bind('tag', $tagId)
                ->fetchObject(self::class);
            if(!$tag)
                throw new ChangelogTagNotFoundException;
            return $tag;
        });
    }
    public static function byChange(ChangelogChange $change): array {
        return DB::prepare(
            self::byQueryBase()
                . ' WHERE `tag_id` IN (SELECT `tag_id` FROM `' . DB::PREFIX . ChangelogChangeTag::TABLE . '` WHERE `change_id` = :change)'
        )->bind('change', $change->getId())->fetchObjects(self::class);
    }
    public static function all(): array {
        return DB::prepare(self::byQueryBase())
            ->fetchObjects(self::class);
    }
}
