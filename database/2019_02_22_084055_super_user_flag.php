<?php
namespace Misuzu\DatabaseMigrations\SuperUserFlag;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_users`
            ADD COLUMN `user_super` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0' AFTER `last_ip`;
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_users`
            DROP COLUMN `user_super`;
    ");
}
