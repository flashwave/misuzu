<?php
namespace Misuzu\DatabaseMigrations\ForumUpdates;

use PDO;

function migrate_up(PDO $conn): void {
    $conn->exec("
        ALTER TABLE `msz_forum_topics`
            ADD COLUMN `topic_count_posts` INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `topic_title`,
            ADD COLUMN `topic_post_first` INT(10) UNSIGNED NULL DEFAULT NULL AFTER `topic_count_views`,
            ADD COLUMN `topic_post_last` INT(10) UNSIGNED NULL DEFAULT NULL AFTER `topic_post_first`,
            DROP COLUMN `poll_id`,
            DROP INDEX `posts_poll_id_foreign`,
            DROP FOREIGN KEY `posts_poll_id_foreign`,
            ADD INDEX `topics_post_first_foreign` (`topic_post_first`),
            ADD INDEX `topics_post_last_foreign` (`topic_post_last`),
            ADD CONSTRAINT `topics_post_first_foreign`
                FOREIGN KEY (`topic_post_first`)
                REFERENCES `msz_forum_posts` (`post_id`)
                ON UPDATE CASCADE
                ON DELETE SET NULL,
            ADD CONSTRAINT `topics_post_last_foreign`
                FOREIGN KEY (`topic_post_last`)
                REFERENCES `msz_forum_posts` (`post_id`)
                ON UPDATE CASCADE
                ON DELETE SET NULL;
    ");

    $conn->exec("
        ALTER TABLE `msz_forum_polls`
            ADD COLUMN `topic_id` INT(10) UNSIGNED NULL DEFAULT NULL AFTER `poll_id`,
            ADD INDEX `forum_poll_topic_foreign` (`topic_id`),
            ADD CONSTRAINT `forum_poll_topic_foreign`
                FOREIGN KEY (`topic_id`)
                REFERENCES `msz_forum_topics` (`topic_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE;
    ");
}

function migrate_down(PDO $conn): void {
    $conn->exec("
        ALTER TABLE `msz_forum_polls`
            DROP COLUMN `topic_id`,
            DROP INDEX `forum_poll_topic_foreign`,
            DROP FOREIGN KEY `forum_poll_topic_foreign`;
    ");

    $conn->exec("
        ALTER TABLE `msz_forum_topics`
            ADD COLUMN `poll_id` INT(10) UNSIGNED NULL DEFAULT NULL AFTER `user_id`,
            DROP COLUMN `topic_count_posts`,
            DROP COLUMN `topic_post_first`,
            DROP COLUMN `topic_post_last`,
            DROP INDEX `topics_post_first_foreign`,
            DROP INDEX `topics_post_last_foreign`,
            DROP FOREIGN KEY `topics_post_first_foreign`,
            DROP FOREIGN KEY `topics_post_last_foreign`,
            ADD INDEX `posts_poll_id_foreign` (`poll_id`),
            ADD CONSTRAINT `posts_poll_id_foreign`
                FOREIGN KEY (`poll_id`)
                REFERENCES `msz_users` (`poll_id`)
                ON UPDATE CASCADE
                ON DELETE SET NULL;
    ");
}
