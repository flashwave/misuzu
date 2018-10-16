<?php
namespace Misuzu\DatabaseMigrations\AddForumAccentColours;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_forum_categories`
            ADD COLUMN `forum_colour` INT UNSIGNED NULL DEFAULT NULL AFTER `forum_description`;
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec('
        ALTER TABLE `msz_forum_categories`
            DROP COLUMN `forum_colour`;
    ');
}
