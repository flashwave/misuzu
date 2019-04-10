<?php
namespace Misuzu\DatabaseMigrations\FixForumIndices;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_forum_categories`
            DROP INDEX `forums_indices`,
            ADD INDEX `forum_order_index` (`forum_order`),
            ADD INDEX `forum_parent_index` (`forum_parent`),
            ADD INDEX `forum_type_index` (`forum_type`);
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_forum_categories`
            DROP INDEX `forum_order_index`,
            DROP INDEX `forum_parent_index`,
            DROP INDEX `forum_type_index`,
            ADD INDEX `forums_indices` (`forum_order`, `forum_parent`, `forum_type`);
    ");
}
