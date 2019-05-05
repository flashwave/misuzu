<?php
namespace Misuzu\DatabaseMigrations\AddForumCategoryIcons;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_forum_categories`
            ADD COLUMN `forum_icon` VARCHAR(50) NULL DEFAULT NULL AFTER `forum_description`;
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_forum_categories`
            DROP COLUMN `forum_icon`;
    ");
}
