<?php
namespace Misuzu\DatabaseMigrations\AddCountryToAuditLog;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec('
        ALTER TABLE `msz_audit_log`
            ADD COLUMN `log_country` CHAR(2) NOT NULL DEFAULT \'XX\' AFTER `log_ip`;
    ');
}

function migrate_down(PDO $conn): void
{
    $conn->exec('
        ALTER TABLE `msz_audit_log`
            DROP COLUMN `log_country`;
    ');
}
