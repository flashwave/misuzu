<?php
namespace Misuzu\DatabaseMigrations\MakeChangelogActionsStatic;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_changelog_changes`
            CHANGE COLUMN `action_id` `change_action` INT(10) UNSIGNED NULL DEFAULT NULL AFTER `user_id`,
            DROP INDEX `changes_user_id_foreign`,
            ADD INDEX `changes_user_foreign` (`user_id`),
            DROP INDEX `changes_action_id_foreign`,
            ADD INDEX `changes_action_index` (`change_action`),
            DROP INDEX `changes_change_created_index`,
            ADD INDEX `changes_created_index` (`change_created`),
            DROP FOREIGN KEY `changes_action_id_foreign`;
    ");

    $conn->exec("DROP TABLE `msz_changelog_actions`");
}

function migrate_down(PDO $conn): void
{
    $conn->exec("
        CREATE TABLE `msz_changelog_actions` (
            `action_id`     INT(10) UNSIGNED    NOT NULL AUTO_INCREMENT,
            `action_name`   VARCHAR(50)         NOT NULL,
            `action_colour` INT(10) UNSIGNED    NOT NULL DEFAULT '0',
            `action_class`  VARCHAR(20)         NOT NULL,
            PRIMARY KEY (`action_id`),
            UNIQUE INDEX `action_class_unique` (`action_class`)
        )
    ");

    $conn->exec("
        INSERT INTO `msz_changelog_actions`
            (`action_id`, `action_name`, `action_colour`, `action_class`)
        VALUES
            (1, 'Added', 1414709, 'add'),
            (2, 'Removed', 14890819, 'remove'),
            (3, 'Updated', 2718602, 'update'),
            (4, 'Fixed', 2973334, 'fix'),
            (5, 'Imported', 2856568, 'import'),
            (6, 'Reverted', 14910021, 'revert');
    ");

    $conn->exec("
        ALTER TABLE `msz_changelog_changes`
            CHANGE COLUMN `change_action` `action_id` INT(10) UNSIGNED NULL DEFAULT NULL AFTER `user_id`,
            DROP INDEX `changes_user_foreign`,
            ADD INDEX `changes_user_id_foreign` (`user_id`),
            DROP INDEX `changes_action_index`,
            ADD INDEX `changes_action_id_foreign` (`action_id`),
            DROP INDEX `changes_created_index`,
            ADD INDEX `changes_change_created_index` (`change_created`),
            ADD CONSTRAINT `changes_action_id_foreign`
                FOREIGN KEY (`action_id`)
                REFERENCES `msz_changelog_actions` (`action_id`)
                ON UPDATE CASCADE
                ON DELETE SET NULL;
    ");
}
