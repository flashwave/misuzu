<?php
namespace Misuzu\DatabaseMigrations\RolesTableExtensions;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_roles`
            CHANGE COLUMN `role_secret` `role_hidden` TINYINT(1) NOT NULL DEFAULT '0' AFTER `role_description`,
            ADD COLUMN `role_can_leave` TINYINT(1) NOT NULL DEFAULT '0' AFTER `role_hidden`;
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_roles`
            CHANGE COLUMN `role_hidden` `role_secret` TINYINT(1) NOT NULL DEFAULT '0' AFTER `role_description`,
            DROP COLUMN `role_can_leave`;
    ");
}
