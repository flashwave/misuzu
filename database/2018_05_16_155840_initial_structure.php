<?php
namespace Misuzu\DatabaseMigrations\InitialStructure;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        CREATE TABLE `msz_roles` (
            `role_id`           INT(10) UNSIGNED    NOT NULL    AUTO_INCREMENT,
            `role_hierarchy`    INT(11)             NOT NULL    DEFAULT '1',
            `role_name`         VARCHAR(255)        NOT NULL,
            `role_title`        VARCHAR(64)         NULL        DEFAULT NULL,
            `role_description`  TEXT                NULL,
            `role_secret`       TINYINT(1)          NOT NULL    DEFAULT '0',
            `role_colour`       INT(11)             NOT NULL    DEFAULT '0',
            `created_at`        TIMESTAMP           NULL        DEFAULT NULL,
            `updated_at`        TIMESTAMP           NULL        DEFAULT NULL,
            PRIMARY KEY (`role_id`)
        )
    ");

    $conn->exec("
        CREATE TABLE `msz_users` (
            `user_id`       INT(10) UNSIGNED    NOT NULL AUTO_INCREMENT,
            `username`      VARCHAR(255)        NOT NULL,
            `password`      VARCHAR(255)        NULL        DEFAULT NULL,
            `email`         VARCHAR(255)        NOT NULL,
            `register_ip`   VARBINARY(16)       NOT NULL,
            `last_ip`       VARBINARY(16)       NOT NULL,
            `user_country`  CHAR(2)             NOT NULL    DEFAULT 'XX',
            `user_chat_key` VARCHAR(32)         NULL        DEFAULT NULL,
            `created_at`    TIMESTAMP           NULL        DEFAULT NULL,
            `updated_at`    TIMESTAMP           NULL        DEFAULT NULL,
            `deleted_at`    TIMESTAMP           NULL        DEFAULT NULL,
            `display_role`  INT(10) UNSIGNED    NULL        DEFAULT NULL,
            `user_website`  VARCHAR(255)        NOT NULL    DEFAULT '',
            `user_twitter`  VARCHAR(20)         NOT NULL    DEFAULT '',
            `user_github`   VARCHAR(40)         NOT NULL    DEFAULT '',
            `user_skype`    VARCHAR(60)         NOT NULL    DEFAULT '',
            `user_discord`  VARCHAR(40)         NOT NULL    DEFAULT '',
            `user_youtube`  VARCHAR(255)        NOT NULL    DEFAULT '',
            `user_steam`    VARCHAR(255)        NOT NULL    DEFAULT '',
            `user_twitchtv` VARCHAR(30)         NOT NULL    DEFAULT '',
            `user_osu`      VARCHAR(20)         NOT NULL    DEFAULT '',
            `user_lastfm`   VARCHAR(20)         NOT NULL    DEFAULT '',
            `user_title`    VARCHAR(64)         NOT NULL    DEFAULT '',
            `last_seen`     TIMESTAMP           NULL        DEFAULT NULL,
            PRIMARY KEY (`user_id`),
            UNIQUE  INDEX   `users_username_unique`         (`username`),
            UNIQUE  INDEX   `users_email_unique`            (`email`),
                    INDEX   `users_display_role_foreign`    (`display_role`),
            CONSTRAINT `users_display_role_foreign`
                FOREIGN KEY (`display_role`)
                REFERENCES `msz_roles` (`role_id`)
                ON UPDATE CASCADE
                ON DELETE SET NULL
        )
    ");

    $conn->exec("
        CREATE TABLE `msz_user_roles` (
            `user_id` INT(10) UNSIGNED NOT NULL,
            `role_id` INT(10) UNSIGNED NOT NULL,
            UNIQUE  INDEX `user_roles_unique`           (`user_id`, `role_id`),
                    INDEX `user_roles_role_id_foreign`  (`role_id`),
            CONSTRAINT `user_roles_role_id_foreign`
                FOREIGN KEY (`role_id`)
                REFERENCES `msz_roles` (`role_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            CONSTRAINT `user_roles_user_id_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        )
    ");

    $conn->exec("
        CREATE TABLE `msz_sessions` (
            `session_id`        INT(10) UNSIGNED    NOT NULL    AUTO_INCREMENT,
            `user_id`           INT(10) UNSIGNED    NOT NULL,
            `session_key`       VARCHAR(255)        NOT NULL,
            `session_ip`        VARBINARY(16)       NOT NULL,
            `user_agent`        VARCHAR(255)        NULL        DEFAULT NULL,
            `expires_on`        TIMESTAMP           NULL        DEFAULT NULL,
            `created_at`        TIMESTAMP           NULL        DEFAULT NULL,
            `updated_at`        TIMESTAMP           NULL        DEFAULT NULL,
            `session_country`   CHAR(2)             NOT NULL    DEFAULT 'XX',
            PRIMARY KEY (`session_id`),
            INDEX `sessions_user_id_foreign` (`user_id`),
            CONSTRAINT `sessions_user_id_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        )
    ");

    $conn->exec("
        CREATE TABLE `msz_login_attempts` (
            `attempt_id`        INT(10) UNSIGNED    NOT NULL    AUTO_INCREMENT,
            `was_successful`    TINYINT(1)          NOT NULL,
            `attempt_ip`        VARBINARY(16)       NOT NULL,
            `attempt_country`   CHAR(2)             NOT NULL    DEFAULT 'XX',
            `user_id`           INT(10) UNSIGNED    NULL        DEFAULT NULL,
            `created_at`        TIMESTAMP           NULL        DEFAULT NULL,
            `updated_at`        TIMESTAMP           NULL        DEFAULT NULL,
            `user_agent`        VARCHAR(255)        NOT NULL    DEFAULT '',
            PRIMARY KEY (`attempt_id`),
            INDEX `login_attempts_user_id_foreign` (`user_id`),
            CONSTRAINT `login_attempts_user_id_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE SET NULL
        )
    ");

    $conn->exec("
        CREATE TABLE `msz_news_categories` (
            `category_id`           INT(10) UNSIGNED    NOT NULL    AUTO_INCREMENT,
            `category_name`         VARCHAR(255)        NOT NULL,
            `category_description`  TEXT                NOT NULL,
            `is_hidden`             TINYINT(1)          NOT NULL    DEFAULT '0',
            `created_at`            TIMESTAMP           NULL        DEFAULT NULL,
            `updated_at`            TIMESTAMP           NULL        DEFAULT NULL,
            PRIMARY KEY (`category_id`)
        )
    ");

    $conn->exec("
        CREATE TABLE `msz_news_posts` (
            `post_id`       INT(10) UNSIGNED    NOT NULL AUTO_INCREMENT,
            `category_id`   INT(10) UNSIGNED    NOT NULL,
            `is_featured`   TINYINT(1)          NOT NULL    DEFAULT '0',
            `user_id`       INT(10) UNSIGNED    NULL        DEFAULT NULL,
            `post_title`    VARCHAR(255)        NOT NULL,
            `post_text`     TEXT                NOT NULL,
            `scheduled_for` TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
            `created_at`    TIMESTAMP           NULL        DEFAULT NULL,
            `updated_at`    TIMESTAMP           NULL        DEFAULT NULL,
            `deleted_at`    TIMESTAMP           NULL        DEFAULT NULL,
            PRIMARY KEY (`post_id`),
            INDEX `news_posts_category_id_foreign` (`category_id`),
            INDEX `news_posts_user_id_foreign` (`user_id`),
            CONSTRAINT `news_posts_category_id_foreign`
                FOREIGN KEY (`category_id`)
                REFERENCES `msz_news_categories` (`category_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            CONSTRAINT `news_posts_user_id_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE SET NULL
        )
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec('DROP TABLE `msz_news_posts`');
    $conn->exec('DROP TABLE `msz_news_categories`');
    $conn->exec('DROP TABLE `msz_login_attempts`');
    $conn->exec('DROP TABLE `msz_sessions`');
    $conn->exec('DROP TABLE `msz_user_roles`');
    $conn->exec('DROP TABLE `msz_users`');
    $conn->exec('DROP TABLE `msz_roles`');
}
