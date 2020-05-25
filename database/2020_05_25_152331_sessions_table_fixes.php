<?php
namespace Misuzu\DatabaseMigrations\SessionsTableFixes;

use PDO;

function migrate_up(PDO $conn): void {
    $conn->exec("
        ALTER TABLE `msz_sessions`
            CHANGE COLUMN `session_key` `session_key` BINARY(64) NOT NULL AFTER `user_id`,
            CHANGE COLUMN `session_expires` `session_expires` TIMESTAMP NOT NULL DEFAULT DATE_ADD(NOW(), INTERVAL 1 MONTH) AFTER `session_country`,
            ADD INDEX `sessions_created_index` (`session_created`);
    ");
}

function migrate_down(PDO $conn): void {
    $conn->exec("
        ALTER TABLE `msz_sessions`
            CHANGE COLUMN `session_key` `session_key` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_bin' AFTER `user_id`,
            CHANGE COLUMN `session_expires` `session_expires` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP() AFTER `session_country`,
            DROP INDEX `sessions_created_index`;
    ");
}
