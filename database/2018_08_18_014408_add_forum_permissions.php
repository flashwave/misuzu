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
}

function migrate_down(PDO $conn): void
{
    $conn->exec('DROP TABLE `msz_forum_permissions`');
}
