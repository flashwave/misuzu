<?php
namespace Misuzu\DatabaseMigrations\AddTfaLoginTokenTable;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        CREATE TABLE `msz_auth_tfa` (
            `user_id`       INT UNSIGNED    NOT NULL,
            `tfa_token`     CHAR(32)        NOT NULL,
            `tfa_created`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE INDEX    `auth_tfa_token_unique`     (`tfa_token`),
            INDEX           `auth_tfa_user_foreign`     (`user_id`),
            INDEX           `auth_tfa_created_index`    (`tfa_created`),
            CONSTRAINT `auth_tfa_user_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        );
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec("
        DROP TABLE `msz_auth_tfa`;
    ");
}
