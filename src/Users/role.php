<?php
use Misuzu\Database;

define('MSZ_ROLE_MAIN', 1);

function user_role_add(int $userId, int $roleId): bool
{
    $addRole = Database::connection()->prepare('
        INSERT INTO `msz_user_roles`
            (`user_id`, `role_id`)
        VALUES
            (:user_id, :role_id)
    ');
    $addRole->bindValue('user_id', $userId);
    $addRole->bindValue('role_id', $roleId);
    return $addRole->execute();
}
