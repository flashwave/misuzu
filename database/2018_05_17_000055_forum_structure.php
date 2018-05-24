<?php
namespace Misuzu\DatabaseMigrations\ForumStructure;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        CREATE TABLE `msz_forum_categories` (
            `forum_id`          INT(10) UNSIGNED    NOT NULL    AUTO_INCREMENT,
            `forum_order`       INT(10) UNSIGNED    NOT NULL    DEFAULT '1',
            `forum_parent`      INT(10) UNSIGNED    NOT NULL    DEFAULT '0',
            `forum_name`        VARCHAR(255)        NOT NULL,
            `forum_type`        TINYINT(4)          NOT NULL    DEFAULT '0',
            `forum_description` TEXT                NULL,
            `forum_link`        VARCHAR(255)        NULL        DEFAULT NULL,
            `forum_link_clicks` INT(10) UNSIGNED    NULL        DEFAULT NULL,
            `forum_created`     TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
            `forum_archived`    TINYINT(1)          NOT NULL    DEFAULT '0',
            `forum_hidden`      TINYINT(1)          NOT NULL    DEFAULT '0',
            PRIMARY KEY (`forum_id`),
            INDEX `forums_indices` (`forum_order`, `forum_parent`, `forum_type`)
        )
    ");

    $conn->exec("
        CREATE TABLE `msz_forum_topics` (
            `topic_id`          INT(10) UNSIGNED    NOT NULL    AUTO_INCREMENT,
            `forum_id`          INT(10) UNSIGNED    NOT NULL,
            `user_id`           INT(10) UNSIGNED    NULL        DEFAULT NULL,
            `topic_type`        TINYINT(4)          NOT NULL    DEFAULT '0',
            `topic_title`       VARCHAR(255)        NOT NULL,
            `topic_created`     TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
            `topic_bumped`      TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
            `topic_deleted`     TIMESTAMP           NULL        DEFAULT NULL,
            `topic_locked`      TIMESTAMP           NULL        DEFAULT NULL,
            PRIMARY KEY (`topic_id`),
            INDEX `topics_forum_id_foreign` (`forum_id`),
            INDEX `topics_user_id_foreign`  (`user_id`),
            INDEX `topics_indices`          (`topic_bumped`, `topic_type`),
            CONSTRAINT `topics_forum_id_foreign`
                FOREIGN KEY (`forum_id`)
                REFERENCES `msz_forum_categories` (`forum_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            CONSTRAINT `topics_user_id_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        )
    ");

    $conn->exec("
        CREATE TABLE `msz_forum_posts` (
            `post_id`       INT(10) UNSIGNED    NOT NULL    AUTO_INCREMENT,
            `topic_id`      INT(10) UNSIGNED    NOT NULL,
            `forum_id`      INT(10) UNSIGNED    NOT NULL,
            `user_id`       INT(10) UNSIGNED    NULL        DEFAULT NULL,
            `post_ip`       BLOB                NOT NULL,
            `post_text`     TEXT                NOT NULL,
            `post_parse`    TINYINT(4) UNSIGNED NOT NULL    DEFAULT '0',
            `post_created`  TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
            `post_edited`   TIMESTAMP           NULL        DEFAULT NULL,
            `post_deleted`  TIMESTAMP           NULL        DEFAULT NULL,
            PRIMARY KEY (`post_id`),
            INDEX `posts_topic_id_foreign`  (`topic_id`),
            INDEX `posts_forum_id_foreign`  (`forum_id`),
            INDEX `posts_user_id_foreign`   (`user_id`),
            INDEX `posts_indices`           (`post_created`),
            CONSTRAINT `posts_topic_id_foreign`
                FOREIGN KEY (`topic_id`)
                REFERENCES `msz_forum_topics` (`topic_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            CONSTRAINT `posts_forum_id_foreign`
                FOREIGN KEY (`forum_id`)
                REFERENCES `msz_forum_categories` (`forum_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            CONSTRAINT `posts_user_id_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE SET NULL
        )
    ");

    $conn->exec("
        CREATE TABLE `msz_forum_topics_track` (
            `user_id`           INT(10) UNSIGNED    NOT NULL,
            `topic_id`          INT(10) UNSIGNED    NOT NULL,
            `forum_id`          INT(10) UNSIGNED    NOT NULL,
            `track_last_read`   TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE  INDEX `topics_track_unique`             (`user_id`, `topic_id`),
                    INDEX `topics_track_topic_id_foreign`   (`topic_id`),
                    INDEX `topics_track_user_id_foreign`    (`user_id`),
                    INDEX `topics_track_forum_id_foreign`   (`forum_id`),
            CONSTRAINT `topics_track_topic_id_foreign`
                FOREIGN KEY (`topic_id`)
                REFERENCES `msz_forum_topics` (`topic_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            CONSTRAINT `topics_track_user_id_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            CONSTRAINT `topics_track_forum_id_foreign`
                FOREIGN KEY (`forum_id`)
                REFERENCES `msz_forum_categories` (`forum_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        )
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec('DROP TABLE `msz_forum_topics_track`');
    $conn->exec('DROP TABLE `msz_forum_posts`');
    $conn->exec('DROP TABLE `msz_forum_topics`');
    $conn->exec('DROP TABLE `msz_forum_categories`');
}
