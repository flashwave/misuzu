<?php
namespace Misuzu\DatabaseMigrations\FixNewsTables;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_news_categories`
            CHANGE COLUMN `is_hidden`   `category_is_hidden`    TINYINT(1)  NOT NULL DEFAULT '0'                AFTER `category_description`,
            CHANGE COLUMN `created_at`  `category_created`      TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP  AFTER `category_is_hidden`,
            DROP COLUMN `updated_at`;
    ");

    $conn->exec("
        ALTER TABLE `msz_news_posts`
            CHANGE COLUMN `comment_section_id`  `comment_section_id`    INT(10) UNSIGNED    NULL        DEFAULT NULL                AFTER `user_id`,
            CHANGE COLUMN `is_featured`         `post_is_featured`      TINYINT(1)          NOT NULL    DEFAULT '0'                 AFTER `comment_section_id`,
            CHANGE COLUMN `scheduled_for`       `post_scheduled`        TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP   AFTER `post_text`,
            CHANGE COLUMN `created_at`          `post_created`          TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP   AFTER `post_scheduled`,
            CHANGE COLUMN `updated_at`          `post_updated`          TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP
                                                                                                        ON UPDATE CURRENT_TIMESTAMP AFTER `post_created`,
            CHANGE COLUMN `deleted_at`          `post_deleted`          TIMESTAMP           NULL        DEFAULT NULL                AFTER `post_updated`,
            ADD INDEX `news_posts_indices` (`post_is_featured`, `post_scheduled`, `post_created`);
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_news_posts`
            CHANGE COLUMN `post_is_featured`    `is_featured`           TINYINT(1)          NOT NULL    DEFAULT '0'                 AFTER `category_id`,
            CHANGE COLUMN `post_scheduled`      `scheduled_for`         TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP   AFTER `post_text`,
            CHANGE COLUMN `post_created`        `created_at`            TIMESTAMP           NULL        DEFAULT NULL                AFTER `scheduled_for`,
            CHANGE COLUMN `post_updated`        `updated_at`            TIMESTAMP           NULL        DEFAULT NULL                AFTER `created_at`,
            CHANGE COLUMN `post_deleted`        `deleted_at`            TIMESTAMP           NULL        DEFAULT NULL                AFTER `updated_at`,
            CHANGE COLUMN `comment_section_id`  `comment_section_id`    INT(10) UNSIGNED    NULL        DEFAULT NULL                AFTER `deleted_at`,
            DROP INDEX `news_posts_indices`;
    ");

    $conn->exec("
        ALTER TABLE `msz_news_categories`
            CHANGE COLUMN `category_is_hidden` `is_hidden` TINYINT(1) NOT NULL DEFAULT '0' AFTER `category_description`,
            CHANGE COLUMN `category_created` `created_at` TIMESTAMP NULL DEFAULT NULL AFTER `is_hidden`,
            ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL AFTER `created_at`;
    ");
}
