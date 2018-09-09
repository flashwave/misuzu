<?php
namespace Misuzu\DatabaseMigrations\AddForumPermissions;

use PDO;

function migrate_up(PDO $conn): void
{
    // this permission system is nearly identical to the global site one, aside from it having a forum_id field

    $conn->exec("
        CREATE TABLE `msz_forum_permissions` (
            `user_id`               INT(10) UNSIGNED NULL       DEFAULT NULL,
            `role_id`               INT(10) UNSIGNED NULL       DEFAULT NULL,
            `forum_id`              INT(10) UNSIGNED NOT NULL,
            `forum_perms_allow`     INT(10) UNSIGNED NOT NULL   DEFAULT '0',
            `forum_perms_deny`      INT(10) UNSIGNED NOT NULL   DEFAULT '0',
            UNIQUE INDEX `forum_permissions_user_id_unique`     (`user_id`),
            UNIQUE INDEX `forum_permissions_role_id_unique`     (`role_id`),
            UNIQUE INDEX `forum_permissions_forum_id_unique`    (`forum_id`),
            CONSTRAINT `forum_permissions_user_id_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            CONSTRAINT `forum_permissions_role_id_foreign`
                FOREIGN KEY (`role_id`)
                REFERENCES `msz_roles` (`role_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            CONSTRAINT `forum_permissions_forum_id_foreign`
                FOREIGN KEY (`forum_id`)
                REFERENCES `msz_forum_categories` (`forum_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        )
    ");

    $conn->exec('
        CREATE VIEW `msz_forum_permissions_view` AS
        WITH RECURSIVE permissions(user_id, role_id, forum_id, forum_perms_allow, forum_perms_deny) as (
            SELECT
                pp.`user_id`, pp.`role_id`,
                pc.`forum_id`,
                IFNULL(pp.`forum_perms_allow`, 0), IFNULL(pp.`forum_perms_deny`, 0)
            FROM `msz_forum_categories` as pc
            LEFT JOIN `msz_forum_permissions` as pp
            ON pp.`forum_id` = pc.`forum_id`
            GROUP BY `user_id`, `role_id`, `forum_id`
            UNION ALL
            SELECT
                permissions.`user_id`, permissions.`role_id`,
                cc.`forum_id`,
                IFNULL(cp.`forum_perms_allow`, 0) | permissions.`forum_perms_allow`,
                IFNULL(cp.`forum_perms_deny`, 0) | permissions.`forum_perms_deny`
            FROM `msz_forum_categories` as cc
            LEFT JOIN `msz_forum_permissions` as cp
            ON cp.`forum_id` = cc.`forum_id`
            INNER JOIN permissions
            ON cc.`forum_parent` = permissions.`forum_id`
        )
        SELECT
            `user_id`, `role_id`, `forum_id`,
            (BIT_OR(`forum_perms_allow`) &~ BIT_OR(`forum_perms_deny`)) as `forum_perms`
        FROM permissions
        GROUP BY `user_id`, `role_id`, `forum_id`
    ');
}

function migrate_down(PDO $conn): void
{
    $conn->exec('DROP VIEW `msz_forum_permissions_view`');
    $conn->exec('DROP TABLE `msz_forum_permissions`');
}
