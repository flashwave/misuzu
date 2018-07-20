<?php
namespace Misuzu\DatabaseMigrations\AddUserColourColumn;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec('
        ALTER TABLE `msz_users`
            ADD COLUMN `user_colour` INT(11) NULL DEFAULT NULL AFTER `user_country`,
            DROP COLUMN `user_chat_key`;
    ');
}

function migrate_down(PDO $conn): void
{
    $conn->exec('
        ALTER TABLE `msz_users`
            DROP COLUMN `user_colour`,
            ADD COLUMN `user_chat_key` VARCHAR(32) NULL DEFAULT NULL AFTER `user_country`;
    ');
}
