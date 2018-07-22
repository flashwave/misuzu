<?php
namespace Misuzu\DatabaseMigrations\AddPasswordResetsTable;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec('
        CREATE TABLE `msz_users_password_resets` (
            `user_id`           INT(10) UNSIGNED    NOT NULL,
            `reset_ip`          VARBINARY(16)       NOT NULL,
            `reset_requested`   TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
            `verification_code` CHAR(12)            NULL        DEFAULT NULL,
            UNIQUE INDEX `msz_users_password_resets_unique` (`user_id`, `reset_ip`),
            INDEX        `msz_users_password_resets_index`  (`reset_requested`),
            CONSTRAINT `msz_users_password_resets_user_id_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        )
    ');
}

function migrate_down(PDO $conn): void
{
    $conn->exec('DROP TABLE `msz_users_password_resets`');
}
