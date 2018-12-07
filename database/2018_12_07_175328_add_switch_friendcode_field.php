<?php
namespace Misuzu\DatabaseMigrations\AddSwitchFriendcodeField;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_users`
            ADD COLUMN `user_ninswitch` VARCHAR(14) NOT NULL DEFAULT '' AFTER `user_steam`;
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_users`
            DROP COLUMN `user_ninswitch`;
    ");
}
