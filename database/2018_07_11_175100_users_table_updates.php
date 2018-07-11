<?php
namespace Misuzu\DatabaseMigrations\UsersTableUpdates;

// gets rid of `updated_at`, ironically

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_users`
            DROP COLUMN `updated_at`,
            ADD INDEX `users_user_country_index` (`user_country`),
            ADD INDEX `users_created_at_index` (`created_at`),
            ADD INDEX `users_last_seen_index` (`last_seen`);
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec('
        ALTER TABLE `msz_users`
            ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL,
            DROP INDEX `users_user_country_index`,
            DROP INDEX `users_created_at_index`,
            DROP INDEX `users_last_seen_index`;
    ');
}
