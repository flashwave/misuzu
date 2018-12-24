<?php
namespace Misuzu\DatabaseMigrations\AddWarningsTable;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        CREATE TABLE `msz_user_warnings` (
            `warning_id`            INT(10) UNSIGNED    NOT NULL    AUTO_INCREMENT,
            `user_id`               INT(10) UNSIGNED    NOT NULL,
            `user_ip`               VARBINARY(16)       NOT NULL,
            `issuer_id`             INT(10) UNSIGNED    NULL        DEFAULT NULL,
            `issuer_ip`             VARBINARY(16)       NOT NULL,
            `warning_created`       TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
            `warning_type`          TINYINT(3) UNSIGNED NOT NULL,
            `warning_note`          VARCHAR(255)        NOT NULL,
            `warning_note_private`  TEXT                NOT NULL,
            PRIMARY KEY (`warning_id`),
            INDEX `user_warnings_user_foreign`      (`user_id`),
            INDEX `user_warnings_issuer_foreign`    (`issuer_id`),
            INDEX `user_warnings_indices`           (`warning_created`, `warning_type`),
            CONSTRAINT `user_warnings_issuer_foreign`
                FOREIGN KEY (`issuer_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE SET NULL,
            CONSTRAINT `user_warnings_user_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        )
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec("
        DROP TABLE `msz_user_warnings`;
    ");
}
