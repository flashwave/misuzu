<?php
namespace Misuzu\DatabaseMigrations\AddProfileAboutSection;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_users`
            ADD COLUMN `user_about_content` TEXT NULL DEFAULT NULL AFTER `display_role`,
            ADD COLUMN `user_about_parser` TINYINT(4) NOT NULL DEFAULT '0' AFTER `user_about_content`;
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec('
        ALTER TABLE `msz_users`
            DROP COLUMN `user_about_content`,
            DROP COLUMN `user_about_parser`;
    ');
}
