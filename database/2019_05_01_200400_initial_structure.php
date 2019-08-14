<?php
namespace Misuzu\DatabaseMigrations\InitialStructure;

use PDO;

// MariaDB migration!
// Gotta get rid of possible incompatible stuff like WITH RECURSIVE for new installations.

function migrate_up(PDO $conn): void {
    $getMigrations = $conn->prepare("SELECT COUNT(*) FROM `msz_migrations`");
    $migrations = (int)($getMigrations->execute() ? $getMigrations->fetchColumn() : 0);

    if($migrations > 0) {
        $conn->exec("TRUNCATE `msz_migrations`");
        return;
    }

    $conn->exec("
        CREATE TABLE `msz_ip_blacklist` (
            `ip_subnet` VARBINARY(16)       NOT NULL,
            `ip_mask`   TINYINT(3) UNSIGNED NOT NULL,
            UNIQUE INDEX `ip_blacklist_unique` (`ip_subnet`, `ip_mask`)
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");

    $conn->exec("
        CREATE TABLE `msz_roles` (
            `role_id`           INT(10) UNSIGNED    NOT NULL    AUTO_INCREMENT,
            `role_hierarchy`    INT(11)             NOT NULL    DEFAULT '1',
            `role_name`         VARCHAR(255)        NOT NULL                    COLLATE 'utf8mb4_bin',
            `role_title`        VARCHAR(64)         NULL        DEFAULT NULL    COLLATE 'utf8mb4_bin',
            `role_description`  TEXT                NULL        DEFAULT NULL    COLLATE 'utf8mb4_bin',
            `role_hidden`       TINYINT(1)          NOT NULL    DEFAULT '0',
            `role_can_leave`    TINYINT(1)          NOT NULL    DEFAULT '0',
            `role_colour`       INT(11)             NULL        DEFAULT NULL,
            `role_created`      TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`role_id`)
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");

    $conn->exec("
        CREATE TABLE `msz_users` (
            `user_id`                   INT(10) UNSIGNED    NOT NULL    AUTO_INCREMENT,
            `username`                  VARCHAR(255)        NOT NULL                    COLLATE 'utf8mb4_bin',
            `password`                  VARCHAR(255)        NULL        DEFAULT NULL    COLLATE 'utf8mb4_bin',
            `email`                     VARCHAR(255)        NOT NULL                    COLLATE 'utf8mb4_bin',
            `register_ip`               VARBINARY(16)       NOT NULL,
            `last_ip`                   VARBINARY(16)       NOT NULL,
            `user_super`                TINYINT(1) UNSIGNED NOT NULL    DEFAULT '0',
            `user_country`              CHAR(2)             NOT NULL    DEFAULT 'XX'    COLLATE 'utf8mb4_bin',
            `user_colour`               INT(11)             NULL        DEFAULT NULL,
            `user_created`              TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
            `user_active`               TIMESTAMP           NULL        DEFAULT NULL,
            `user_deleted`              TIMESTAMP           NULL        DEFAULT NULL,
            `display_role`              INT(10) UNSIGNED    NULL        DEFAULT NULL,
            `user_totp_key`             CHAR(26)            NULL        DEFAULT NULL    COLLATE 'utf8mb4_bin',
            `user_about_content`        TEXT                NULL        DEFAULT NULL    COLLATE 'utf8mb4_bin',
            `user_about_parser`         TINYINT(4)          NOT NULL    DEFAULT '0',
            `user_signature_content`    TEXT                NULL        DEFAULT NULL    COLLATE 'utf8mb4_bin',
            `user_signature_parser`     TINYINT(4)          NOT NULL    DEFAULT '0',
            `user_birthdate`            DATE                NULL        DEFAULT NULL,
            `user_background_settings`  TINYINT(4)          NULL        DEFAULT '0',
            `user_website`              VARCHAR(255)        NOT NULL    DEFAULT ''      COLLATE 'utf8mb4_bin',
            `user_twitter`              VARCHAR(20)         NOT NULL    DEFAULT ''      COLLATE 'utf8mb4_bin',
            `user_github`               VARCHAR(40)         NOT NULL    DEFAULT ''      COLLATE 'utf8mb4_bin',
            `user_skype`                VARCHAR(60)         NOT NULL    DEFAULT ''      COLLATE 'utf8mb4_bin',
            `user_discord`              VARCHAR(40)         NOT NULL    DEFAULT ''      COLLATE 'utf8mb4_bin',
            `user_youtube`              VARCHAR(255)        NOT NULL    DEFAULT ''      COLLATE 'utf8mb4_bin',
            `user_steam`                VARCHAR(255)        NOT NULL    DEFAULT ''      COLLATE 'utf8mb4_bin',
            `user_ninswitch`            VARCHAR(14)         NOT NULL    DEFAULT ''      COLLATE 'utf8mb4_bin',
            `user_twitchtv`             VARCHAR(30)         NOT NULL    DEFAULT ''      COLLATE 'utf8mb4_bin',
            `user_osu`                  VARCHAR(20)         NOT NULL    DEFAULT ''      COLLATE 'utf8mb4_bin',
            `user_lastfm`               VARCHAR(20)         NOT NULL    DEFAULT ''      COLLATE 'utf8mb4_bin',
            `user_title`                VARCHAR(64)         NULL        DEFAULT NULL    COLLATE 'utf8mb4_bin',
            PRIMARY KEY (`user_id`),
            UNIQUE  INDEX `users_username_unique`       (`username`),
            UNIQUE  INDEX `users_email_unique`          (`email`),
                    INDEX `users_display_role_foreign`  (`display_role`),
                    INDEX `users_indices` (
                        `user_country`, `user_created`, `user_active`,
                        `user_deleted`, `user_birthdate`
                    ),
            CONSTRAINT `users_display_role_foreign`
                FOREIGN KEY (`display_role`)
                REFERENCES `msz_roles` (`role_id`)
                ON UPDATE CASCADE
                ON DELETE SET NULL
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
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
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");

    $conn->exec("
        CREATE TABLE `msz_users_password_resets` (
            `user_id`           INT(10) UNSIGNED    NOT NULL,
            `reset_ip`          VARBINARY(16)       NOT NULL,
            `reset_requested`   TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
            `verification_code` CHAR(12)            NULL        DEFAULT NULL                COLLATE 'utf8mb4_bin',
            UNIQUE  INDEX `msz_users_password_resets_unique`    (`user_id`, `reset_ip`),
                    INDEX `msz_users_password_resets_index`     (`reset_requested`),
            CONSTRAINT `msz_users_password_resets_user_id_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");

    $conn->exec("
        CREATE TABLE `msz_user_relations` (
            `user_id`           INT(10) UNSIGNED    NOT NULL,
            `subject_id`        INT(10) UNSIGNED    NOT NULL,
            `relation_type`     TINYINT(3) UNSIGNED NOT NULL,
            `relation_created`  TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE  INDEX `user_relations_unique`               (`user_id`, `subject_id`),
                    INDEX `user_relations_subject_id_foreign`   (`subject_id`),
            CONSTRAINT `user_relations_subject_id_foreign`
                FOREIGN KEY (`subject_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            CONSTRAINT `user_relations_user_id_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");

    $conn->exec("
        CREATE TABLE `msz_user_warnings` (
            `warning_id`            INT(10) UNSIGNED    NOT NULL    AUTO_INCREMENT,
            `user_id`               INT(10) UNSIGNED    NOT NULL,
            `user_ip`               VARBINARY(16)       NOT NULL,
            `issuer_id`             INT(10) UNSIGNED    NULL        DEFAULT NULL,
            `issuer_ip`             VARBINARY(16)       NOT NULL,
            `warning_created`       TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
            `warning_duration`      TIMESTAMP           NULL        DEFAULT NULL,
            `warning_type`          TINYINT(3) UNSIGNED NOT NULL,
            `warning_note`          VARCHAR(255)        NOT NULL                    COLLATE 'utf8mb4_bin',
            `warning_note_private`  TEXT                NULL        DEFAULT NULL    COLLATE 'utf8mb4_bin',
            PRIMARY KEY (`warning_id`),
            INDEX `user_warnings_user_foreign`      (`user_id`),
            INDEX `user_warnings_issuer_foreign`    (`issuer_id`),
            INDEX `user_warnings_created_index`     (`warning_created`),
            INDEX `user_warnings_duration_index`    (`warning_duration`),
            INDEX `user_warnings_type_index`        (`warning_type`),
            INDEX `user_warnings_user_ip_index`     (`user_ip`),
            CONSTRAINT `user_warnings_issuer_foreign`
                FOREIGN KEY (`issuer_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE SET NULL,
            CONSTRAINT `user_warnings_user_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");

    $conn->exec("
        CREATE TABLE `msz_sessions` (
            `session_id`            INT(10) UNSIGNED    NOT NULL    AUTO_INCREMENT,
            `user_id`               INT(10) UNSIGNED    NOT NULL,
            `session_key`           VARCHAR(255)        NOT NULL                    COLLATE 'utf8mb4_bin',
            `session_ip`            VARBINARY(16)       NOT NULL,
            `session_ip_last`       VARBINARY(16)       NULL        DEFAULT NULL,
            `session_user_agent`    VARCHAR(255)        NOT NULL                    COLLATE 'utf8mb4_bin',
            `session_country`       CHAR(2)             NOT NULL    DEFAULT 'XX'    COLLATE 'utf8mb4_bin',
            `session_expires`       TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `session_expires_bump`  TINYINT(3) UNSIGNED NOT NULL    DEFAULT '1',
            `session_created`       TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
            `session_active`        TIMESTAMP           NULL        DEFAULT NULL,
            PRIMARY KEY (`session_id`),
            UNIQUE  INDEX `sessions_key_unique`         (`session_key`),
                    INDEX `sessions_user_id_foreign`    (`user_id`),
                    INDEX `sessions_expires_index`      (`session_expires`),
            CONSTRAINT `sessions_user_id_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");

    $conn->exec("
        CREATE TABLE `msz_permissions` (
            `user_id`               INT(10) UNSIGNED NULL       DEFAULT NULL,
            `role_id`               INT(10) UNSIGNED NULL       DEFAULT NULL,
            `general_perms_allow`   INT(10) UNSIGNED NOT NULL   DEFAULT '0',
            `general_perms_deny`    INT(10) UNSIGNED NOT NULL   DEFAULT '0',
            `user_perms_allow`      INT(10) UNSIGNED NOT NULL   DEFAULT '0',
            `user_perms_deny`       INT(10) UNSIGNED NOT NULL   DEFAULT '0',
            `changelog_perms_allow` INT(10) UNSIGNED NOT NULL   DEFAULT '0',
            `changelog_perms_deny`  INT(10) UNSIGNED NOT NULL   DEFAULT '0',
            `news_perms_allow`      INT(10) UNSIGNED NOT NULL   DEFAULT '0',
            `news_perms_deny`       INT(10) UNSIGNED NOT NULL   DEFAULT '0',
            `forum_perms_allow`     INT(10) UNSIGNED NOT NULL   DEFAULT '0',
            `forum_perms_deny`      INT(10) UNSIGNED NOT NULL   DEFAULT '0',
            `comments_perms_allow`  INT(10) UNSIGNED NOT NULL   DEFAULT '0',
            `comments_perms_deny`   INT(10) UNSIGNED NOT NULL   DEFAULT '0',
            UNIQUE INDEX `permissions_user_id_unique` (`user_id`),
            UNIQUE INDEX `permissions_role_id_unique` (`role_id`),
            CONSTRAINT `permissions_role_id_foreign`
                FOREIGN KEY (`role_id`)
                REFERENCES `msz_roles` (`role_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            CONSTRAINT `permissions_user_id_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");

    $conn->exec("
        CREATE TABLE `msz_audit_log` (
            `log_id`        INT(10) UNSIGNED    NOT NULL    AUTO_INCREMENT,
            `user_id`       INT(10) UNSIGNED    NULL        DEFAULT NULL,
            `log_action`    VARCHAR(50)         NOT NULL                    COLLATE 'utf8mb4_bin',
            `log_params`    TEXT                NOT NULL                    COLLATE 'utf8mb4_bin',
            `log_created`   TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
            `log_ip`        VARBINARY(16)       NULL        DEFAULT NULL,
            `log_country`   CHAR(2)             NOT NULL    DEFAULT 'XX'    COLLATE 'utf8mb4_bin',
            PRIMARY KEY (`log_id`),
            INDEX `audit_log_user_id_foreign` (`user_id`),
            CONSTRAINT `audit_log_user_id_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");

    $conn->exec("
        CREATE TABLE `msz_auth_tfa` (
            `user_id`       INT(10) UNSIGNED    NOT NULL,
            `tfa_token`     CHAR(32)            NOT NULL COLLATE 'utf8mb4_bin',
            `tfa_created`   TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE  INDEX `auth_tfa_token_unique`   (`tfa_token`),
                    INDEX `auth_tfa_user_foreign`   (`user_id`),
                    INDEX `auth_tfa_created_index`  (`tfa_created`),
            CONSTRAINT `auth_tfa_user_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");

    $conn->exec("
        CREATE TABLE `msz_login_attempts` (
            `attempt_id`            INT(10) UNSIGNED    NOT NULL    AUTO_INCREMENT,
            `user_id`               INT(10) UNSIGNED    NULL        DEFAULT NULL,
            `attempt_success`       TINYINT(1)          NOT NULL,
            `attempt_ip`            VARBINARY(16)       NOT NULL,
            `attempt_country`       CHAR(2)             NOT NULL    DEFAULT 'XX'    COLLATE 'utf8mb4_bin',
            `attempt_created`       TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
            `attempt_user_agent`    VARCHAR(255)        NOT NULL    DEFAULT ''      COLLATE 'utf8mb4_bin',
            PRIMARY KEY (`attempt_id`),
            INDEX `login_attempts_user_id_foreign` (`user_id`),
            CONSTRAINT `login_attempts_user_id_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE SET NULL
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");

    $conn->exec("
        CREATE TABLE `msz_comments_categories` (
            `category_id`       INT(10) UNSIGNED    NOT NULL    AUTO_INCREMENT,
            `category_name`     VARCHAR(255)        NOT NULL    COLLATE 'utf8mb4_bin',
            `category_created`  TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
            `category_locked`   TIMESTAMP           NULL        DEFAULT NULL,
            PRIMARY KEY (`category_id`),
            UNIQUE  INDEX `comments_categories_name_unique`     (`category_name`),
                    INDEX `comments_categories_locked_index`    (`category_locked`)
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");

    $conn->exec("
        CREATE TABLE `msz_comments_posts` (
            `comment_id`        INT(10) UNSIGNED    NOT NULL    AUTO_INCREMENT,
            `category_id`       INT(10) UNSIGNED    NOT NULL,
            `user_id`           INT(10) UNSIGNED    NULL        DEFAULT NULL,
            `comment_reply_to`  INT(10) UNSIGNED    NULL        DEFAULT NULL,
            `comment_text`      TEXT                NOT NULL    COLLATE 'utf8mb4_bin',
            `comment_created`   TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
            `comment_pinned`    TIMESTAMP           NULL        DEFAULT NULL,
            `comment_edited`    TIMESTAMP           NULL        DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            `comment_deleted`   TIMESTAMP           NULL        DEFAULT NULL,
            PRIMARY KEY (`comment_id`),
            INDEX `comments_posts_category_foreign` (`category_id`),
            INDEX `comments_posts_user_foreign`     (`user_id`),
            INDEX `comments_posts_reply_id`         (`comment_reply_to`),
            INDEX `comments_posts_dates` (
                `comment_created`, `comment_pinned`,
                `comment_edited`, `comment_deleted`
            ),
            CONSTRAINT `comments_posts_category_foreign`
                FOREIGN KEY (`category_id`)
                REFERENCES `msz_comments_categories` (`category_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            CONSTRAINT `comments_posts_user_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE SET NULL
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");

    $conn->exec("
        CREATE TABLE `msz_comments_votes` (
            `comment_id`    INT(10) UNSIGNED    NOT NULL,
            `user_id`       INT(10) UNSIGNED    NOT NULL,
            `comment_vote`  TINYINT(4)          NOT NULL DEFAULT '0',
            UNIQUE  INDEX `comments_vote_unique`        (`comment_id`, `user_id`),
                    INDEX `comments_vote_user_foreign`  (`user_id`),
                    INDEX `comments_vote_index`         (`comment_vote`),
            CONSTRAINT `comment_vote_id`
                FOREIGN KEY (`comment_id`)
                REFERENCES `msz_comments_posts` (`comment_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            CONSTRAINT `comment_vote_user`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");

    $conn->exec("
        CREATE TABLE `msz_news_categories` (
            `category_id`           INT(10) UNSIGNED    NOT NULL AUTO_INCREMENT,
            `category_name`         VARCHAR(255)        NOT NULL COLLATE 'utf8mb4_bin',
            `category_description`  TEXT                NOT NULL COLLATE 'utf8mb4_bin',
            `category_is_hidden`    TINYINT(1)          NOT NULL DEFAULT '0',
            `category_created`      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`category_id`)
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");

    $conn->exec("
        CREATE TABLE `msz_news_posts` (
            `post_id`               INT(10) UNSIGNED    NOT NULL    AUTO_INCREMENT,
            `category_id`           INT(10) UNSIGNED    NOT NULL,
            `user_id`               INT(10) UNSIGNED    NULL        DEFAULT NULL,
            `comment_section_id`    INT(10) UNSIGNED    NULL        DEFAULT NULL,
            `post_is_featured`      TINYINT(1)          NOT NULL    DEFAULT '0',
            `post_title`            VARCHAR(255)        NOT NULL    COLLATE 'utf8mb4_bin',
            `post_text`             TEXT                NOT NULL    COLLATE 'utf8mb4_bin',
            `post_scheduled`        TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
            `post_created`          TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
            `post_updated`          TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `post_deleted`          TIMESTAMP           NULL        DEFAULT NULL,
            PRIMARY KEY (`post_id`),
                        INDEX `news_posts_category_id_foreign`  (`category_id`),
                        INDEX `news_posts_user_id_foreign`      (`user_id`),
                        INDEX `news_posts_comment_section`      (`comment_section_id`),
                        INDEX `news_posts_featured_index`       (`post_is_featured`),
                        INDEX `news_posts_scheduled_index`      (`post_scheduled`),
                        INDEX `news_posts_created_index`        (`post_created`),
                        INDEX `news_posts_updated_index`        (`post_updated`),
                        INDEX `news_posts_deleted_index`        (`post_deleted`),
            FULLTEXT    INDEX `news_posts_fulltext`             (`post_title`, `post_text`),
            CONSTRAINT `news_posts_category_id_foreign`
                FOREIGN KEY (`category_id`)
                REFERENCES `msz_news_categories` (`category_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            CONSTRAINT `news_posts_comment_section`
                FOREIGN KEY (`comment_section_id`)
                REFERENCES `msz_comments_categories` (`category_id`)
                ON UPDATE CASCADE
                ON DELETE SET NULL,
            CONSTRAINT `news_posts_user_id_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE SET NULL
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");

    $conn->exec("
        CREATE TABLE `msz_changelog_tags` (
            `tag_id`            INT(10) UNSIGNED    NOT NULL    AUTO_INCREMENT,
            `tag_name`          VARCHAR(255)        NOT NULL                    COLLATE 'utf8mb4_bin',
            `tag_description`   TEXT                NULL        DEFAULT NULL    COLLATE 'utf8mb4_bin',
            `tag_created`       TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
            `tag_archived`      TIMESTAMP           NULL        DEFAULT NULL,
            PRIMARY KEY (`tag_id`),
            UNIQUE  INDEX `tag_name`        (`tag_name`),
                    INDEX `tag_archived`    (`tag_archived`)
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");

    $conn->exec("
        CREATE TABLE `msz_changelog_changes` (
            `change_id`         INT(10) UNSIGNED    NOT NULL    AUTO_INCREMENT,
            `user_id`           INT(10) UNSIGNED    NULL        DEFAULT NULL,
            `change_action`     INT(10) UNSIGNED    NULL        DEFAULT NULL,
            `change_created`    TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
            `change_log`        VARCHAR(255)        NOT NULL                    COLLATE 'utf8mb4_bin',
            `change_text`       TEXT                NULL        DEFAULT NULL    COLLATE 'utf8mb4_bin',
            PRIMARY KEY (`change_id`),
            INDEX `changes_user_foreign`    (`user_id`),
            INDEX `changes_action_index`    (`change_action`),
            INDEX `changes_created_index`   (`change_created`),
            CONSTRAINT `changes_user_id_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE SET NULL
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");

    $conn->exec("
        CREATE TABLE `msz_changelog_change_tags` (
            `change_id` INT(10) UNSIGNED NOT NULL,
            `tag_id`    INT(10) UNSIGNED NOT NULL,
            UNIQUE  INDEX `change_tag_unique`   (`change_id`, `tag_id`),
                    INDEX `tag_id_foreign_key`  (`tag_id`),
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
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");

    $conn->exec("
        CREATE TABLE `msz_forum_polls` (
            `poll_id`               INT(10) UNSIGNED    NOT NULL    AUTO_INCREMENT,
            `poll_max_votes`        TINYINT(3) UNSIGNED NOT NULL    DEFAULT '1',
            `poll_expires`          TIMESTAMP           NULL        DEFAULT NULL,
            `poll_preview_results`  TINYINT(3) UNSIGNED NOT NULL    DEFAULT '1',
            `poll_change_vote`      TINYINT(3) UNSIGNED NOT NULL    DEFAULT '0',
            PRIMARY KEY (`poll_id`)
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
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
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");

    $conn->exec("
        CREATE TABLE `msz_forum_polls_answers` (
            `user_id`   INT(10) UNSIGNED NOT NULL,
            `poll_id`   INT(10) UNSIGNED NOT NULL,
            `option_id` INT(10) UNSIGNED NOT NULL,
            UNIQUE  INDEX `polls_answers_unique`            (`user_id`, `poll_id`, `option_id`),
                    INDEX `polls_answers_user_foreign`      (`user_id`),
                    INDEX `polls_answers_poll_foreign`      (`poll_id`),
                    INDEX `polls_answers_option_foreign`    (`option_id`),
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
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");

    $conn->exec("
        CREATE TABLE `msz_forum_categories` (
            `forum_id`              INT(10) UNSIGNED    NOT NULL    AUTO_INCREMENT,
            `forum_order`           INT(10) UNSIGNED    NOT NULL    DEFAULT '1',
            `forum_parent`          INT(10) UNSIGNED    NOT NULL    DEFAULT '0',
            `forum_name`            VARCHAR(255)        NOT NULL                    COLLATE 'utf8mb4_bin',
            `forum_type`            TINYINT(4)          NOT NULL    DEFAULT '0',
            `forum_description`     TEXT                NULL        DEFAULT NULL    COLLATE 'utf8mb4_bin',
            `forum_colour`          INT(10) UNSIGNED    NULL        DEFAULT NULL,
            `forum_link`            VARCHAR(255)        NULL        DEFAULT NULL    COLLATE 'utf8mb4_bin',
            `forum_link_clicks`     INT(10) UNSIGNED    NULL        DEFAULT NULL,
            `forum_created`         TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
            `forum_archived`        TINYINT(1)          NOT NULL    DEFAULT '0',
            `forum_hidden`          TINYINT(1)          NOT NULL    DEFAULT '0',
            `forum_count_topics`    INT(10) UNSIGNED    NOT NULL    DEFAULT '0',
            `forum_count_posts`     INT(10) UNSIGNED    NOT NULL    DEFAULT '0',
            PRIMARY KEY (`forum_id`),
            INDEX `forum_order_index`   (`forum_order`),
            INDEX `forum_parent_index`  (`forum_parent`),
            INDEX `forum_type_index`    (`forum_type`)
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");

    $conn->exec("
        CREATE TABLE `msz_forum_permissions` (
            `user_id`           INT(10) UNSIGNED NULL       DEFAULT NULL,
            `role_id`           INT(10) UNSIGNED NULL       DEFAULT NULL,
            `forum_id`          INT(10) UNSIGNED NOT NULL,
            `forum_perms_allow` INT(10) UNSIGNED NOT NULL   DEFAULT '0',
            `forum_perms_deny`  INT(10) UNSIGNED NOT NULL   DEFAULT '0',
            UNIQUE  INDEX `forum_permissions_unique`    (`user_id`, `role_id`, `forum_id`),
                    INDEX `forum_permissions_forum_id`  (`forum_id`),
                    INDEX `forum_permissions_role_id`   (`role_id`),
            CONSTRAINT `forum_permissions_forum_id_foreign`
                FOREIGN KEY (`forum_id`)
                REFERENCES `msz_forum_categories` (`forum_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            CONSTRAINT `forum_permissions_role_id_foreign`
                FOREIGN KEY (`role_id`)
                REFERENCES `msz_roles` (`role_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            CONSTRAINT `forum_permissions_user_id_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");

    $conn->exec("
        CREATE TABLE `msz_forum_topics` (
            `topic_id`          INT(10) UNSIGNED    NOT NULL    AUTO_INCREMENT,
            `forum_id`          INT(10) UNSIGNED    NOT NULL,
            `user_id`           INT(10) UNSIGNED    NULL        DEFAULT NULL,
            `poll_id`           INT(10) UNSIGNED    NULL        DEFAULT NULL,
            `topic_type`        TINYINT(4)          NOT NULL    DEFAULT '0',
            `topic_title`       VARCHAR(255)        NOT NULL    COLLATE 'utf8mb4_bin',
            `topic_count_views` INT(10) UNSIGNED    NOT NULL    DEFAULT '0',
            `topic_created`     TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
            `topic_bumped`      TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
            `topic_deleted`     TIMESTAMP           NULL        DEFAULT NULL,
            `topic_locked`      TIMESTAMP           NULL        DEFAULT NULL,
            PRIMARY KEY (`topic_id`),
                        INDEX `topics_forum_id_foreign` (`forum_id`),
                        INDEX `topics_user_id_foreign`  (`user_id`),
                        INDEX `topics_type_index`       (`topic_type`),
                        INDEX `topics_created_index`    (`topic_created`),
                        INDEX `topics_bumped_index`     (`topic_bumped`),
                        INDEX `topics_deleted_index`    (`topic_deleted`),
                        INDEX `topics_locked_index`     (`topic_locked`),
                        INDEX `posts_poll_id_foreign`   (`poll_id`),
            FULLTEXT    INDEX `topics_fulltext`         (`topic_title`),
            CONSTRAINT `posts_poll_id_foreign`
                FOREIGN KEY (`poll_id`)
                REFERENCES `msz_forum_polls` (`poll_id`)
                ON UPDATE CASCADE
                ON DELETE SET NULL,
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
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
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
                    INDEX `forum_track_last_read`           (`track_last_read`),
            CONSTRAINT `topics_track_forum_id_foreign`
                FOREIGN KEY (`forum_id`)
                REFERENCES `msz_forum_categories` (`forum_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            CONSTRAINT `topics_track_topic_id_foreign`
                FOREIGN KEY (`topic_id`)
                REFERENCES `msz_forum_topics` (`topic_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            CONSTRAINT `topics_track_user_id_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");

    $conn->exec("
        CREATE TABLE `msz_forum_posts` (
            `post_id`                   INT(10) UNSIGNED    NOT NULL    AUTO_INCREMENT,
            `topic_id`                  INT(10) UNSIGNED    NOT NULL,
            `forum_id`                  INT(10) UNSIGNED    NOT NULL,
            `user_id`                   INT(10) UNSIGNED    NULL        DEFAULT NULL,
            `post_ip`                   VARBINARY(16)       NOT NULL,
            `post_text`                 TEXT                NOT NULL    COLLATE 'utf8mb4_bin',
            `post_parse`                TINYINT(4) UNSIGNED NOT NULL    DEFAULT '0',
            `post_display_signature`    TINYINT(4) UNSIGNED NOT NULL    DEFAULT '1',
            `post_created`              TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
            `post_edited`               TIMESTAMP           NULL        DEFAULT NULL,
            `post_deleted`              TIMESTAMP           NULL        DEFAULT NULL,
            PRIMARY KEY (`post_id`),
                        INDEX `posts_topic_id_foreign`          (`topic_id`),
                        INDEX `posts_forum_id_foreign`          (`forum_id`),
                        INDEX `posts_user_id_foreign`           (`user_id`),
                        INDEX `posts_created_index`             (`post_created`),
                        INDEX `posts_deleted_index`             (`post_deleted`),
                        INDEX `posts_parse_index`               (`post_parse`),
                        INDEX `posts_edited_index`              (`post_edited`),
                        INDEX `posts_display_signature_index`   (`post_display_signature`),
                        INDEX `posts_ip_index`                  (`post_ip`),
            FULLTEXT    INDEX `posts_fulltext`                  (`post_text`),
            CONSTRAINT `posts_forum_id_foreign`
                FOREIGN KEY (`forum_id`)
                REFERENCES `msz_forum_categories` (`forum_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            CONSTRAINT `posts_topic_id_foreign`
                FOREIGN KEY (`topic_id`)
                REFERENCES `msz_forum_topics` (`topic_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            CONSTRAINT `posts_user_id_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE SET NULL
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");
}

function migrate_down(PDO $conn): void {
    $conn->exec("DROP TABLE `msz_forum_posts`");
    $conn->exec("DROP TABLE `msz_forum_topics_track`");
    $conn->exec("DROP TABLE `msz_forum_topics`");
    $conn->exec("DROP TABLE `msz_forum_permissions`");
    $conn->exec("DROP TABLE `msz_forum_categories`");
    $conn->exec("DROP TABLE `msz_forum_polls_answers`");
    $conn->exec("DROP TABLE `msz_forum_polls_options`");
    $conn->exec("DROP TABLE `msz_forum_polls`");
    $conn->exec("DROP TABLE `msz_changelog_change_tags`");
    $conn->exec("DROP TABLE `msz_changelog_changes`");
    $conn->exec("DROP TABLE `msz_changelog_tags`");
    $conn->exec("DROP TABLE `msz_news_posts`");
    $conn->exec("DROP TABLE `msz_news_categories`");
    $conn->exec("DROP TABLE `msz_comments_votes`");
    $conn->exec("DROP TABLE `msz_comments_posts`");
    $conn->exec("DROP TABLE `msz_comments_categories`");
    $conn->exec("DROP TABLE `msz_login_attempts`");
    $conn->exec("DROP TABLE `msz_auth_tfa`");
    $conn->exec("DROP TABLE `msz_audit_log`");
    $conn->exec("DROP TABLE `msz_permissions`");
    $conn->exec("DROP TABLE `msz_sessions`");
    $conn->exec("DROP TABLE `msz_user_warnings`");
    $conn->exec("DROP TABLE `msz_user_relations`");
    $conn->exec("DROP TABLE `msz_users_password_resets`");
    $conn->exec("DROP TABLE `msz_user_roles`");
    $conn->exec("DROP TABLE `msz_users`");
    $conn->exec("DROP TABLE `msz_roles`");
    $conn->exec("DROP TABLE `msz_ip_blacklist`");
}
