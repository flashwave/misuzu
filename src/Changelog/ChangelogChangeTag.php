<?php
namespace Misuzu\Changelog;

use JsonSerializable;
use Misuzu\DB;

class ChangelogChangeTagException extends ChangelogException {}
class ChangelogChangeTagNotFoundException extends ChangelogChangeTagException {}
class ChangelogChangeCreationFailedException extends ChangelogChangeTagException {}

class ChangelogChangeTag implements JsonSerializable {
    // Database fields
    private $change_id = -1;
    private $tag_id = -1;

    private $change = null;
    private $tag = null;

    public const TABLE = 'changelog_change_tags';
    private const QUERY_SELECT = 'SELECT %1$s FROM `' . DB::PREFIX . self::TABLE . '` AS '. self::TABLE;
    private const SELECT = '%1$s.`change_id`, %1$s.`tag_id`';

    public function getChangeId(): int {
        return $this->change_id < 1 ? -1 : $this->change_id;
    }
    public function getChange(): ChangelogChange {
        if($this->change === null)
            $this->change = ChangelogChange::byId($this->getChangeId());
        return $this->change;
    }

    public function getTagId(): int {
        return $this->tag_id < 1 ? -1 : $this->tag_id;
    }
    public function getTag(): ChangelogTag {
        if($this->tag === null)
            $this->tag = ChangelogTag::byId($this->getTagId());
        return $this->tag;
    }

    public function jsonSerialize() {
        return [
            'change' => $this->getChangeId(),
            'tag'    => $this->getTagId(),
        ];
    }

    public static function create(ChangelogChange $change, ChangelogTag $tag, bool $return = false): ?self {
        $createRelation = DB::prepare(
            'REPLACE INTO `' . DB::PREFIX . self::TABLE . '` (`change_id`, `tag_id`)'
            . ' VALUES (:change, :tag)'
        )->bind('change', $change->getId())->bind('tag', $tag->getId());

        if(!$createRelation->execute())
            throw new ChangelogChangeCreationFailedException;
        if(!$return)
            return null;

        return self::byExact($change, $tag);
    }

    public static function purgeChange(ChangelogChange $change): void {
        DB::prepare(
            'DELETE FROM `' . DB::PREFIX . self::TABLE . '` WHERE `change_id` = :change'
        )->bind('change', $change->getId())->execute();
    }

    private static function countQueryBase(string $column): string {
        return sprintf(self::QUERY_SELECT, sprintf('COUNT(%s.`%s`)', self::TABLE, $column));
    }
    public static function countByTag(ChangelogTag $tag): int {
        return (int)DB::prepare(
            self::countQueryBase('change_id')
                . ' WHERE `tag_id` = :tag'
        )->bind('tag', $tag->getId())->fetchColumn();
    }

    private static function byQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf(self::SELECT, self::TABLE));
    }
    public static function byExact(ChangelogChange $change, ChangelogTag $tag): array {
        $tag = DB::prepare(self::byQueryBase() . ' WHERE `tag_id` = :tag')
            ->bind('change', $change->getId())
            ->bind('tag', $tag->getId())
            ->fetchObject(self::class);
        if(!$tag)
            throw new ChangelogChangeTagNotFoundException;
        return $tag;
    }
    public static function byChange(ChangelogChange $change): array {
        return DB::prepare(
            self::byQueryBase()
                . ' WHERE `change_id` = :change'
        )->bind('change', $change->getId())->fetchObjects(self::class);
    }
}
