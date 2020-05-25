<?php
namespace Misuzu\DatabaseMigrations\LoginAttemptsTableFixes;

use PDO;

function migrate_up(PDO $conn): void {
    $conn->exec("
        ALTER TABLE `msz_login_attempts`
            DROP COLUMN `attempt_id`,
            ADD INDEX `login_attempts_created_index` (`attempt_created`);
    ");
}

function migrate_down(PDO $conn): void {
    $conn->exec("
        ALTER TABLE `msz_login_attempts`
            ADD COLUMN `attempt_id` INT UNSIGNED NOT NULL AUTO_INCREMENT FIRST,
            DROP INDEX `login_attempts_created_index`,
            ADD PRIMARY KEY (`attempt_id`);
    ");
}
