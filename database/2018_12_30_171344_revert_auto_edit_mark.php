<?php
namespace Misuzu\DatabaseMigrations\RevertAutoEditMark;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_forum_posts`
            CHANGE COLUMN `post_edited` `post_edited` TIMESTAMP NULL DEFAULT NULL AFTER `post_created`;
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_forum_posts`
            CHANGE COLUMN `post_edited` `post_edited` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `post_created`;
    ");
}
