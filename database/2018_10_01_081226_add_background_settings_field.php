<?php
namespace Misuzu\DatabaseMigrations\AddBackgroundSettingsField;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_users`
            ADD COLUMN `user_background_settings` TINYINT(4) DEFAULT '0' AFTER `user_about_parser`;
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec('
        ALTER TABLE `msz_users`
            DROP COLUMN `user_background_settings`;
    ');
}
