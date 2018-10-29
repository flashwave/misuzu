<?php
namespace Misuzu\DatabaseMigrations\UndoLaravelDamage;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_login_attempts`
            ALTER `was_successful` DROP DEFAULT;
    ");

    $conn->exec("
        ALTER TABLE `msz_login_attempts`
            CHANGE COLUMN `user_id` `user_id` INT(10) UNSIGNED NULL DEFAULT NULL AFTER `attempt_id`,
            CHANGE COLUMN `was_successful` `attempt_success` TINYINT(1) NOT NULL AFTER `user_id`,
            CHANGE COLUMN `created_at` `attempt_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `attempt_country`,
            CHANGE COLUMN `user_agent` `attempt_user_agent` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb4_bin' AFTER `attempt_created`,
            DROP COLUMN `updated_at`;
    ");

    $conn->exec("
        ALTER TABLE `msz_roles`
            CHANGE COLUMN `created_at` `role_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `role_colour`,
            DROP COLUMN `updated_at`;
    ");

    $conn->exec("
        ALTER TABLE `msz_sessions`
            ALTER `user_agent` DROP DEFAULT,
            ALTER `expires_on` DROP DEFAULT;
    ");

    $conn->exec("
        ALTER TABLE `msz_sessions`
            CHANGE COLUMN `user_agent` `session_user_agent` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_bin' AFTER `session_ip`,
            CHANGE COLUMN `session_country` `session_country` CHAR(2) NOT NULL DEFAULT 'XX' COLLATE 'utf8mb4_bin' AFTER `session_user_agent`,
            CHANGE COLUMN `expires_on` `session_expires` TIMESTAMP NOT NULL AFTER `session_country`,
            CHANGE COLUMN `created_at` `session_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `session_expires`,
            CHANGE COLUMN `updated_at` `session_active` TIMESTAMP NULL DEFAULT NULL AFTER `session_created`;
    ");

    $conn->exec("
        ALTER TABLE `msz_users`
            CHANGE COLUMN `created_at` `user_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `user_colour`,
            CHANGE COLUMN `last_seen` `user_active` TIMESTAMP NULL DEFAULT NULL AFTER `user_created`,
            CHANGE COLUMN `deleted_at` `user_deleted` TIMESTAMP NULL DEFAULT NULL AFTER `user_active`,
            DROP INDEX `users_user_country_index`,
            DROP INDEX `users_last_seen_index`,
            DROP INDEX `users_created_at_index`,
            ADD INDEX `users_indices` (`user_country`, `user_created`, `user_active`, `user_deleted`);
    ");
}

function migrate_down(PDO $conn): void
{
}
