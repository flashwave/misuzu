<?php
namespace Misuzu\Users;

use ArrayAccess;
use Misuzu\Colour;
use Misuzu\DB;
use Misuzu\HasRankInterface;
use Misuzu\Memoizer;
use Misuzu\Pagination;

class UserRoleException extends UsersException {}
class UserRoleNotFoundException extends UserRoleException {}
class UserRoleCreationFailedException extends UserRoleException {}

class UserRole implements ArrayAccess, HasRankInterface {
    public const DEFAULT = 1;

    // Database fields
    private $role_id = -1;
    private $role_hierarchy = 1;
    private $role_name = '';
    private $role_title = null;
    private $role_description = null;
    private $role_hidden = 0;
    private $role_can_leave = 0;
    private $role_colour = null;
    private $role_created = null;

    public const TABLE = 'roles';
    private const QUERY_SELECT = 'SELECT %1$s FROM `' . DB::PREFIX . self::TABLE . '` AS '. self::TABLE;
    private const SELECT = '%1$s.`role_id`, %1$s.`role_hierarchy`, %1$s.`role_name`, %1$s.`role_title`, %1$s.`role_description`'
                         . ', %1$s.`role_hidden`, %1$s.`role_can_leave`, %1$s.`role_colour`'
                         . ', UNIX_TIMESTAMP(%1$s.`role_created`) AS `role_created`';

    private $colour = null;
    private $users = [];
    private $userCount = -1;

    public function getId(): int {
        return $this->role_id < 1 ? -1 : $this->role_id;
    }

    public function getRank(): int {
        return $this->role_hierarchy;
    }
    public function setRank(int $rank): self {
        $this->role_hierarchy = $rank;
        return $this;
    }

    public function getName(): string {
        return $this->role_name;
    }
    public function setName(string $name): self {
        $this->role_name = $name;
        return $this;
    }

    public function getTitle(): string {
        return $this->role_title ?? '';
    }
    public function setTitle(string $title): self {
        $this->role_title = empty($title) ? null : $title;
        return $this;
    }

    public function getDescription(): string {
        return $this->role_description ?? '';
    }
    public function setDescription(string $description): self {
        $this->role_description = empty($description) ? null : $description;
        return $this;
    }

    public function isHidden(): bool {
        return boolval($this->role_hidden);
    }
    public function setHidden(bool $hidden): self {
        $this->role_hidden = $hidden ? 1 : 0;
        return $this;
    }

    public function getCanLeave(): bool {
        return boolval($this->role_can_leave);
    }
    public function setCanLeave(bool $canLeave): bool {
        $this->role_can_leave = $canLeave ? 1 : 0;
        return $this;
    }

    // Provided just because, avoid using these for validations sake
    public function getColourRaw(): ?int {
        return $this->role_colour;
    }
    public function setColourRaw(?int $colour): self {
        $this->role_colour = $colour;
        return $this;
    }

    public function getColour(): Colour {
        if($this->colour === null || ($this->getColourRaw() ?? 0x40000000) !== $this->colour->getRaw())
            $this->colour = new Colour($this->role_colour ?? 0x40000000);
        return $this->colour;
    }
    public function setColour(Colour $colour): self {
        $this->role_colour = $colour->getInherit() ? null : $colour->getRaw();
        $this->colour = $this->colour;
        return $this;
    }

    public function getCreatedTime(): int {
        return $this->role_created === null ? -1 : $this->role_created;
    }

    public function getUserCount(): int {
        if($this->userCount < 0)
            $this->userCount = UserRoleRelation::countUsers($this);
        return $this->userCount;
    }

    public function isDefault(): bool {
        return $this->getId() === self::DEFAULT;
    }

    public function hasAuthorityOver(HasRankInterface $other): bool {
        if($other instanceof User && $other->isSuper())
            return false;
        return $this->getRank() > $other->getRank();
    }

    public function save(): void {
        $isInsert = $this->role_id < 1;
        if($isInsert) {
            $set = DB::prepare(
                'INSERT INTO `' . DB::PREFIX . self::TABLE . '` (`role_hierarchy`, `role_name`, `role_title`, `role_description`, `role_hidden`, `role_can_leave`, `role_colour`)'
                . ' VALUES (:rank, :name, :title, :desc, :hide, :can_leave, :colour)'
            );
        } else {
            $set = DB::prepare(
                'UPDATE `' . DB::PREFIX . self::TABLE . '` SET'
                . ' `role_hierarchy` = :rank, `role_name` = :name, `role_title` = :title,'
                . ' `role_description` = :desc, `role_hidden` = :hide, `role_can_leave` = :can_leave, `role_colour` = :colour'
                . ' WHERE `role_id` = :role'
            )->bind('role', $this->role_id);
        }

        $set->bind('rank', $this->role_hierarchy)
            ->bind('name', $this->role_name)
            ->bind('title', $this->role_title)
            ->bind('desc', $this->role_description)
            ->bind('hide', $this->role_hidden)
            ->bind('can_leave', $this->role_can_leave)
            ->bind('colour', $this->role_colour);

        if($isInsert) {
            $this->role_id = $set->executeGetId();
            $this->role_created = time();
        } else $set->execute();
    }

    private static function countQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf('COUNT(*)', self::TABLE));
    }
    public static function countAll(bool $showHidden = false): int {
        return (int)DB::prepare(
            self::countQueryBase()
            . ($showHidden ? '' : ' WHERE `role_hidden` = 0')
        )->fetchColumn();
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
    public static function byId(int $roleId): self {
        $object = DB::prepare(
            self::byQueryBase() . ' WHERE `role_id` = :role'
        )   ->bind('role', $roleId)
            ->fetchObject(self::class);
        if(!$object)
            throw new UserRoleNotFoundException;
        return $object;
    }
    public static function byDefault(): self {
        return self::byId(self::DEFAULT);
    }
    public static function all(bool $showHidden = false, ?Pagination $pagination = null): array {
        $query = self::byQueryBase();

        if(!$showHidden)
            $query .= ' WHERE `role_hidden` = 0';

        if($pagination !== null)
            $query .= ' LIMIT :range OFFSET :offset';

        $getObjects = DB::prepare($query);

        if($pagination !== null)
            $getObjects->bind('range', $pagination->getRange())
                ->bind('offset', $pagination->getOffset());

        return $getObjects->fetchObjects(self::class);
    }

    // to satisfy the fucked behaviour array_diff has
    public function __toString() {
        return md5($this->getId() . '#' . $this->getName());
    }

    // Twig shim for the roles list on the members page, don't use this class as an array normally.
    public function offsetExists($offset): bool {
        return $offset === 'name' || $offset === 'id';
    }
    public function offsetGet($offset) {
        return $this->{'get' . ucfirst($offset)}();
    }
    public function offsetSet($offset, $value) {}
    public function offsetUnset($offset) {}
}
