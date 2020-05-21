<?php
namespace Misuzu\DatabaseMigrations\AuditLogTableFixes;

use PDO;

function migrate_up(PDO $conn): void {
    $conn->exec('
        ALTER TABLE `msz_audit_log`
            DROP COLUMN `log_id`,
            ADD INDEX `audit_log_created_index` (`log_created`);
    ');
}

function migrate_down(PDO $conn): void {
    $conn->exec("
        ALTER TABLE `msz_audit_log`
            ADD COLUMN `log_id` INT UNSIGNED NOT NULL AUTO_INCREMENT FIRST,
            DROP INDEX `audit_log_created_index`,
            ADD PRIMARY KEY (`log_id`);
    ");
}
