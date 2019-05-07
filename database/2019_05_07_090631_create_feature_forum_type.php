<?php
namespace Misuzu\DatabaseMigrations\CreateFeatureForumType;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        CREATE TABLE `msz_forum_topics_priority` (
            `topic_id`          INT(10) UNSIGNED    NOT NULL,
            `user_id`           INT(10) UNSIGNED    NOT NULL,
            `topic_priority`    SMALLINT(6)         NOT NULL,
            UNIQUE  INDEX `forum_topics_priority_unique`        (`topic_id`, `user_id`),
                    INDEX `forum_topics_priority_topic_foreign` (`topic_id`),
                    INDEX `forum_topics_priority_user_foreign`  (`user_id`),
            CONSTRAINT `forum_topics_priority_topic_foreign`
                FOREIGN KEY (`topic_id`)
                REFERENCES `msz_forum_topics` (`topic_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            CONSTRAINT `forum_topics_priority_user_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec("
        DROP TABLE ...
    ");
}
