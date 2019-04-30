<?php
namespace Misuzu\DatabaseMigrations\RemoveWithRecursive;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec('DROP VIEW `msz_forum_permissions_view`');
}

function migrate_down(PDO $conn): void
{
    $conn->exec("
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
    ");
}
