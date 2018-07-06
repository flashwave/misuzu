<?php
namespace Misuzu\DatabaseMigrations\ChangelogTables;

use PDO;

function migrate_up(PDO $conn): void
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
        CREATE TABLE `msz_changelog_changes` (
            `change_id`         INT(10) UNSIGNED    NOT NULL    AUTO_INCREMENT,
            `user_id`           INT(10) UNSIGNED    NULL        DEFAULT NULL,
            `action_id`         INT(10) UNSIGNED    NULL        DEFAULT NULL,
            `change_created`    TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
            `change_log`        VARCHAR(255)        NOT NULL,
            `change_text`       TEXT                NULL,
            PRIMARY KEY (`change_id`),
            INDEX `changes_user_id_foreign`         (`user_id`),
            INDEX `changes_action_id_foreign`       (`action_id`),
            INDEX `changes_change_created_index`    (`change_created`),
            CONSTRAINT `changes_action_id_foreign`
                FOREIGN KEY (`action_id`)
                REFERENCES `msz_changelog_actions` (`action_id`)
                ON UPDATE CASCADE
                ON DELETE SET NULL,
            CONSTRAINT `changes_user_id_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE SET NULL
        )
    ");

    $conn->exec("
        CREATE TABLE `msz_changelog_tags` (
            `tag_id`            INT(10) UNSIGNED    NOT NULL    AUTO_INCREMENT,
            `tag_name`          VARCHAR(255)        NOT NULL,
            `tag_description`   TEXT                NULL,
            `tag_created`       TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`tag_id`),
            UNIQUE INDEX `tag_name` (`tag_name`)
        )
    ");

    $conn->exec("
        CREATE TABLE `msz_changelog_change_tags` (
            `change_id` INT(10) UNSIGNED NOT NULL,
            `tag_id`    INT(10) UNSIGNED NOT NULL,
            INDEX `tag_id_foreign_key` (`tag_id`),
            INDEX `change_tag_constraint` (`change_id`, `tag_id`),
            CONSTRAINT `change_id_foreign_key`
                FOREIGN KEY (`change_id`)
                REFERENCES `msz_changelog_changes` (`change_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            CONSTRAINT `tag_id_foreign_key`
                FOREIGN KEY (`tag_id`)
                REFERENCES `msz_changelog_tags` (`tag_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        )
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec('DROP TABLE `msz_changelog_change_tags`');
    $conn->exec('DROP TABLE `msz_changelog_tags`');
    $conn->exec('DROP TABLE `msz_changelog_changes`');
    $conn->exec('DROP TABLE `msz_changelog_actions`');
}
