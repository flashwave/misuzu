<?php
namespace Misuzu\DatabaseMigrations\AddTwoFactorAuth;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_users`
            ADD COLUMN `user_totp_key` CHAR(26) NULL DEFAULT NULL AFTER `display_role`;
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_users`
            DROP COLUMN `user_totp_key`;
    ");
}
