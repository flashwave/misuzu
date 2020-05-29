<?php
namespace Misuzu\DatabaseMigrations\CaseInsensitiveForumSearch;

use PDO;

function migrate_up(PDO $conn): void {
    $conn->exec("
        ALTER TABLE `msz_forum_topics`
            CHANGE COLUMN `topic_title` `topic_title` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci' AFTER `topic_type`;
    ");

    $conn->exec("
        ALTER TABLE `msz_forum_posts`
            CHANGE COLUMN `post_text` `post_text` TEXT(65535) NOT NULL COLLATE 'utf8mb4_unicode_ci' AFTER `post_ip`;
    ");
}

function migrate_down(PDO $conn): void {
    $conn->exec("
        ALTER TABLE `msz_forum_topics`
            CHANGE COLUMN `topic_title` `topic_title` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_bin' AFTER `topic_type`;
    ");

    $conn->exec("
        ALTER TABLE `msz_forum_posts`
            CHANGE COLUMN `post_text` `post_text` TEXT(65535) NOT NULL COLLATE 'utf8mb4_bin' AFTER `post_ip`;
    ");
}
