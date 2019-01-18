<?php
namespace Misuzu\DatabaseMigrations\AddBirthdayField;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_users`
            ADD COLUMN `user_birthdate` DATE NULL DEFAULT NULL AFTER `user_about_parser`,
            DROP INDEX `users_indices`,
            ADD INDEX `users_indices` (`user_country`, `user_created`, `user_active`, `user_deleted`, `user_birthdate`);
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_users`
            DROP COLUMN `user_birthdate`;
    ");
}
