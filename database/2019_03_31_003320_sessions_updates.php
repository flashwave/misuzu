<?php
namespace Misuzu\DatabaseMigrations\SessionsUpdates;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_sessions`
            ADD COLUMN `session_ip_last` VARBINARY(16) NULL DEFAULT NULL AFTER `session_ip`,
            ADD COLUMN `session_expires_bump` TINYINT UNSIGNED NOT NULL DEFAULT '1' AFTER `session_expires`,
            ADD UNIQUE INDEX `sessions_key_unique` (`session_key`),
            ADD INDEX `sessions_expires_index` (`session_expires`);
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_sessions`
            DROP COLUMN `session_ip_last`,
            DROP COLUMN `session_expires_bump`,
            DROP INDEX `sessions_expires_index`,
            DROP INDEX `sessions_key_unique`;
    ");
}
