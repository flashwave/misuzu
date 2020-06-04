<?php
namespace Misuzu\Users;

use Misuzu\DB;
use Misuzu\Pagination;

class UserRoleRelation {
    // Database fields
    private $user_id = -1;
    private $role_id = -1;

    public const TABLE = 'user_roles';
    private const QUERY_SELECT = 'SELECT %1$s FROM `' . DB::PREFIX . self::TABLE . '` AS '. self::TABLE;
    private const SELECT = '%1$s.`user_id`, %1$s.`role_id`';

    private $user = null;
    private $role = null;

    public function getUserId(): int {
        return $this->user_id < 1 ? -1 : $this->user_id;
    }
    public function getUser(): User {
        if($this->user === null)
            $this->user = User::byId($this->getUserId());
        return $this->user;
    }

    public function getRoleId(): int {
        return $this->role_id < 1 ? -1 : $this->role_id;
    }
    public function getRole(): UserRole {
        if($this->role === null)
            $this->role = UserRole::byId($this->getRoleId());
        return $this->role;
    }

    public function delete(): void {
        self::destroy($this->getUser(), $this->getRole());
    }

    public static function destroy(User $user, UserRole $role): void {
        DB::prepare('DELETE FROM `' . DB::PREFIX . self::TABLE . '` WHERE `user_id` = :user AND `role_id` = :role')
            ->bind('user', $user->getId())
            ->bind('role', $role->getId())
            ->execute();
    }

    public static function purge(User $user): void {
        DB::prepare('DELETE FROM `' . DB::PREFIX . self::TABLE . '`  WHERE `user_id` = :user')
            ->bind('user', $user->getId())
            ->execute();
    }

    public static function create(User $user, UserRole $role): self {
        $create = DB::prepare(
            'REPLACE INTO `' . DB::PREFIX . self::TABLE . '` (`user_id`, `role_id`)'
            . ' VALUES (:user, :role)'
        )   ->bind('user', $user->getId())
            ->bind('role', $role->getId())
            ->execute();

        // data is predictable, just create a "fake"
        $object = new static;
        $object->user = $user;
        $object->user_id = $user->getId();
        $object->role = $role;
        $object->role_id = $role->getId();
        return $object;
    }

    private static function countQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf('COUNT(*)', self::TABLE));
    }
    public static function countUsers(UserRole $role): int {
        return (int)DB::prepare(self::countQueryBase() . ' WHERE `role_id` = :role')
            ->bind('role', $role->getId())
            ->fetchColumn();
    }

    private static function byQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf(self::SELECT, self::TABLE));
    }
    public static function byUser(User $user): array {
        return DB::prepare(self::byQueryBase() . ' WHERE `user_id` = :user')
            ->bind('user', $user->getId())
            ->fetchObjects(self::class);
    }
}
