<?php
namespace Misuzu\DatabaseMigrations\AddStaticForumCounts;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_forum_categories`
            ADD COLUMN `forum_count_topics` INT UNSIGNED NOT NULL DEFAULT '0' AFTER `forum_hidden`,
            ADD COLUMN `forum_count_posts` INT UNSIGNED NOT NULL DEFAULT '0' AFTER `forum_count_topics`;
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_forum_categories`
            DROP COLUMN `forum_count_topics`,
            DROP COLUMN `forum_count_posts`;
    ");
}
