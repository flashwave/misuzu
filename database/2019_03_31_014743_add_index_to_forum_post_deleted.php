<?php
namespace Misuzu\DatabaseMigrations\AddIndexToForumPostDeleted;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_forum_posts`
            DROP INDEX `posts_indices`,
            ADD INDEX `posts_created_index` (`post_created`),
            ADD INDEX `posts_deleted_index` (`post_deleted`);
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_forum_posts`
            DROP INDEX `posts_created_index`,
            DROP INDEX `posts_deleted_index`,
            ADD INDEX `posts_indices` (`post_created`);
    ");
}
