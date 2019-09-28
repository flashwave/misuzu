<?php
define('MSZ_ROLE_MAIN', 1);

function user_role_add(int $userId, int $roleId): bool {
    $addRole = \Misuzu\DB::prepare('
        INSERT INTO `msz_user_roles`
            (`user_id`, `role_id`)
        VALUES
            (:user_id, :role_id)
    ');
    $addRole->bind('user_id', $userId);
    $addRole->bind('role_id', $roleId);
    return $addRole->execute();
}

function user_role_remove(int $userId, int $roleId): bool {
    $removeRole = \Misuzu\DB::prepare('
        DELETE FROM `msz_user_roles`
        WHERE `user_id` = :user_id
        AND `role_id` = :role_id
    ');
    $removeRole->bind('user_id', $userId);
    $removeRole->bind('role_id', $roleId);
    return $removeRole->execute();
}

function user_role_can_leave(int $roleId): bool {
    $canLeaveRole = \Misuzu\DB::prepare('
        SELECT `role_can_leave` != 0
        FROM `msz_roles`
        WHERE `role_id` = :role_id
    ');
    $canLeaveRole->bind('role_id', $roleId);
    return (bool)$canLeaveRole->fetchColumn();
}

function user_role_has(int $userId, int $roleId): bool {
    $hasRole = \Misuzu\DB::prepare('
        SELECT COUNT(`role_id`) > 0
        FROM `msz_user_roles`
        WHERE `user_id` = :user_id
        AND `role_id` = :role_id
    ');
    $hasRole->bind('user_id', $userId);
    $hasRole->bind('role_id', $roleId);
    return (bool)$hasRole->fetchColumn();
}

function user_role_set_display(int $userId, int $roleId): bool {
    if(!user_role_has($userId, $roleId)) {
        return false;
    }

    $setDisplay = \Misuzu\DB::prepare('
        UPDATE `msz_users`
        SET `display_role` = :role_id
        WHERE `user_id` = :user_id
    ');
    $setDisplay->bind('user_id', $userId);
    $setDisplay->bind('role_id', $roleId);

    return $setDisplay->execute();
}

function user_role_get_display(int $userId): int {
    if($userId < 1) {
        return MSZ_ROLE_MAIN;
    }

    $fetchRole = \Misuzu\DB::prepare('
        SELECT `display_role`
        FROM `msz_users`
        WHERE `user_id` = :user_id
    ');
    $fetchRole->bind('user_id', $userId);
    return (int)$fetchRole->fetchColumn(0, MSZ_ROLE_MAIN);
}

function user_role_all_user(int $userId): array {
    $getUserRoles = \Misuzu\DB::prepare('
        SELECT
            r.`role_id`, r.`role_name`, r.`role_description`,
            r.`role_colour`, r.`role_can_leave`, r.`role_created`
        FROM `msz_user_roles` AS ur
        LEFT JOIN `msz_roles` AS r
        ON r.`role_id` = ur.`role_id`
        WHERE ur.`user_id` = :user_id
        ORDER BY r.`role_hierarchy` DESC
    ');
    $getUserRoles->bind('user_id', $userId);
    return $getUserRoles->fetchAll();
}

function user_role_all(bool $withHidden = false) {
    return \Misuzu\DB::query(sprintf(
        '
            SELECT
                r.`role_id`, r.`role_name`, r.`role_description`,
                r.`role_colour`, r.`role_can_leave`, r.`role_created`,
                (
                    SELECT COUNT(`user_id`)
                    FROM `msz_user_roles`
                    WHERE `role_id` = r.`role_id`
                ) AS `role_user_count`
            FROM `msz_roles` AS r
            %s
            ORDER BY `role_id`
        ',
        $withHidden ? '' : 'WHERE `role_hidden` = 0'
    ))->fetchAll();
}

function user_role_get(int $roleId): array {
    $getRole = \Misuzu\DB::prepare('
        SELECT
            r.`role_id`, r.`role_name`, r.`role_description`,
            r.`role_colour`, r.`role_can_leave`, r.`role_created`,
            (
                SELECT COUNT(`user_id`)
                FROM `msz_user_roles`
                WHERE `role_id` = r.`role_id`
            ) AS `role_user_count`
        FROM `msz_roles` AS r
        WHERE `role_id` = :role_id
    ');
    $getRole->bind('role_id', $roleId);
    return $getRole->fetch();
}

function user_role_check_authority(int $userId, int $roleId): bool {
    $checkHierarchy = \Misuzu\DB::prepare('
        SELECT (
            SELECT MAX(r.`role_hierarchy`)
            FROM `msz_roles` AS r
            LEFT JOIN `msz_user_roles` AS ur
            ON ur.`role_id` = r.`role_id`
            WHERE ur.`user_id` = :user_id
        ) > (
            SELECT `role_hierarchy`
            FROM `msz_roles`
            WHERE `role_id` = :role_id
        )
    ');
    $checkHierarchy->bind('user_id', $userId);
    $checkHierarchy->bind('role_id', $roleId);
    return (bool)$checkHierarchy->fetchColumn();
}
