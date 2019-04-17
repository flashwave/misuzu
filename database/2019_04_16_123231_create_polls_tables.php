<?php
namespace Misuzu\DatabaseMigrations\CreatePollsTables;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        CREATE TABLE `msz_forum_polls` (
            `poll_id`               INT(10)     UNSIGNED    NOT NULL    AUTO_INCREMENT,
            `poll_max_votes`        TINYINT(3)  UNSIGNED    NOT NULL    DEFAULT '1',
            `poll_expires`          TIMESTAMP               NULL        DEFAULT NULL,
            `poll_preview_results`  TINYINT(3)  UNSIGNED    NOT NULL    DEFAULT '1',
            PRIMARY KEY (`poll_id`)
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB
    ");

    $conn->exec("
        CREATE TABLE `msz_forum_polls_options` (
            `option_id`     INT(10) UNSIGNED    NOT NULL AUTO_INCREMENT,
            `poll_id`       INT(10) UNSIGNED    NOT NULL,
            `option_text`   VARCHAR(255)        NOT NULL COLLATE 'utf8mb4_bin',
            PRIMARY KEY (`option_id`),
            INDEX `polls_options_poll_foreign` (`poll_id`),
            CONSTRAINT `polls_options_poll_foreign`
                FOREIGN KEY (`poll_id`)
                REFERENCES `msz_forum_polls` (`poll_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB
    ");

    $conn->exec("
        CREATE TABLE `msz_forum_polls_answers` (
            `user_id`   INT(10) UNSIGNED NOT NULL,
            `poll_id`   INT(10) UNSIGNED NOT NULL,
            `option_id` INT(10) UNSIGNED NOT NULL,
            UNIQUE INDEX `polls_answers_unique` (`user_id`, `poll_id`, `option_id`),
            INDEX `polls_answers_user_foreign` (`user_id`),
            INDEX `polls_answers_poll_foreign` (`poll_id`),
            INDEX `polls_answers_option_foreign` (`option_id`),
            CONSTRAINT `polls_answers_option_foreign`
                FOREIGN KEY (`option_id`)
                REFERENCES `msz_forum_polls_options` (`option_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            CONSTRAINT `polls_answers_poll_foreign`
                FOREIGN KEY (`poll_id`)
                REFERENCES `msz_forum_polls` (`poll_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            CONSTRAINT `polls_answers_user_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB
    ");

    $conn->exec("
        ALTER TABLE `msz_forum_topics`
            ADD COLUMN `poll_id` INT(10) UNSIGNED NULL DEFAULT NULL AFTER `user_id`,
            ADD INDEX `posts_poll_id_foreign` (`poll_id`),
            ADD CONSTRAINT `posts_poll_id_foreign`
                FOREIGN KEY (`poll_id`)
                REFERENCES `msz_forum_polls` (`poll_id`)
                ON UPDATE CASCADE
                ON DELETE SET NULL;
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_forum_topics`
            DROP COLUMN `poll_id`,
            DROP INDEX `posts_poll_id_foreign`,
            DROP FOREIGN KEY `posts_poll_id_foreign`;
    ");
    $conn->exec("DROP TABLE `msz_forum_polls_answers`");
    $conn->exec("DROP TABLE `msz_forum_polls_options`");
    $conn->exec("DROP TABLE `msz_forum_polls`");
}
