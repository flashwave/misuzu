<?php
define('MSZ_ROLE_MAIN', 1);

function user_role_add(int $userId, int $roleId): bool
{
    $addRole = db_prepare('
        INSERT INTO `msz_user_roles`
            (`user_id`, `role_id`)
        VALUES
            (:user_id, :role_id)
    ');
    $addRole->bindValue('user_id', $userId);
    $addRole->bindValue('role_id', $roleId);
    return $addRole->execute();
}

function user_role_remove(int $userId, int $roleId): bool
{
    $removeRole = db_prepare('
        DELETE FROM `msz_user_roles`
        WHERE `user_id` = :user_id
        AND `role_id` = :role_id
    ');
    $removeRole->bindValue('user_id', $userId);
    $removeRole->bindValue('role_id', $roleId);
    return $removeRole->execute();
}

function user_role_can_leave(int $roleId): bool
{
    $canLeaveRole = db_prepare('
        SELECT `role_can_leave` != 0
        FROM `msz_roles`
        WHERE `role_id` = :role_id
    ');
    $canLeaveRole->bindValue('role_id', $roleId);
    return $canLeaveRole->execute() ? (bool)$canLeaveRole->fetchColumn() : false;
}

function user_role_has(int $userId, int $roleId): bool
{
    $hasRole = db_prepare('
        SELECT COUNT(`role_id`) > 0
        FROM `msz_user_roles`
        WHERE `user_id` = :user_id
        AND `role_id` = :role_id
    ');
    $hasRole->bindValue('user_id', $userId);
    $hasRole->bindValue('role_id', $roleId);
    return $hasRole->execute() ? (bool)$hasRole->fetchColumn() : false;
}

function user_role_set_display(int $userId, int $roleId): bool
{
    if (!user_role_has($userId, $roleId)) {
        return false;
    }

    $setDisplay = db_prepare('
        UPDATE `msz_users`
        SET `display_role` = :role_id
        WHERE `user_id` = :user_id
    ');
    $setDisplay->bindValue('user_id', $userId);
    $setDisplay->bindValue('role_id', $roleId);

    return $setDisplay->execute();
}

function user_role_get_display(int $userId): int
{
    if ($userId < 1) {
        return MSZ_ROLE_MAIN;
    }

    $fetchRole = db_prepare('
        SELECT `display_role`
        FROM `msz_users`
        WHERE `user_id` = :user_id
    ');
    $fetchRole->bindValue('user_id', $userId);
    return $fetchRole->execute() ? (int)$fetchRole->fetchColumn() : MSZ_ROLE_MAIN;
}
