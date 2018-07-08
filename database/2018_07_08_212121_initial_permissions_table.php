<?php
namespace Misuzu\DatabaseMigrations\InitialPermissionsTable;

use PDO;

function migrate_up(PDO $conn): void
{
    // if you need new permission sets, create a migration that adds a new column to this table.

    $conn->exec("
        CREATE TABLE `msz_permissions` (
            `user_id`               INT(10) UNSIGNED NULL       DEFAULT NULL,
            `role_id`               INT(10) UNSIGNED NULL       DEFAULT NULL,
            `user_perms_allow`      INT(10) UNSIGNED NOT NULL   DEFAULT '0',
            `user_perms_deny`       INT(10) UNSIGNED NOT NULL   DEFAULT '0',
            `changelog_perms_allow` INT(10) UNSIGNED NOT NULL   DEFAULT '0',
            `changelog_perms_deny`  INT(10) UNSIGNED NOT NULL   DEFAULT '0',
            `news_perms_allow`      INT(10) UNSIGNED NOT NULL   DEFAULT '0',
            `news_perms_deny`       INT(10) UNSIGNED NOT NULL   DEFAULT '0',
            UNIQUE INDEX `user_id` (`user_id`),
            UNIQUE INDEX `role_id` (`role_id`),
            CONSTRAINT `role_id_foreign`
                FOREIGN KEY (`role_id`)
                REFERENCES `msz_roles` (`role_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            CONSTRAINT `user_id_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        )
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec('DROP TABLE `msz_permissions`');
}
