<?php
namespace Misuzu\DatabaseMigrations\RecoveryTableFixes;

use PDO;

function migrate_up(PDO $conn): void {
    $conn->exec("
        ALTER TABLE `msz_users_password_resets`
            CHANGE COLUMN `verification_code` `verification_code` CHAR(12) NULL DEFAULT NULL COLLATE 'ascii_bin' AFTER `reset_requested`,
            DROP INDEX `msz_users_password_resets_unique`,
            ADD UNIQUE INDEX `users_password_resets_user_unique` (`user_id`, `reset_ip`),
            DROP INDEX `msz_users_password_resets_index`,
            ADD INDEX `users_password_resets_created_index` (`reset_requested`),
            ADD UNIQUE INDEX `users_password_resets_token_unique` (`verification_code`);
    ");
}

function migrate_down(PDO $conn): void {
    $conn->exec("
        ALTER TABLE `msz_users_password_resets`
            CHANGE COLUMN `verification_code` `verification_code` CHAR(12) NULL DEFAULT NULL COLLATE 'utf8mb4_bin' AFTER `reset_requested`,
            DROP INDEX `users_password_resets_user_unique`,
            ADD UNIQUE INDEX `msz_users_password_resets_unique` (`user_id`, `reset_ip`),
            DROP INDEX `users_password_resets_created_index`,
            ADD INDEX `msz_users_password_resets_index` (`reset_requested`),
            DROP INDEX `users_password_resets_token_unique`;
    ");
}
