<?php
namespace Misuzu\DatabaseMigrations\ChatQuotesTable;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        CREATE TABLE `msz_chat_quotes` (
            `quote_id`          INT(10) UNSIGNED    NOT NULL    AUTO_INCREMENT,
            `quote_parent`      INT(10) UNSIGNED    NULL        DEFAULT NULL,
            `quote_user_id`     INT(10) UNSIGNED    NULL        DEFAULT NULL,
            `quote_username`    VARCHAR(30)         NOT NULL,
            `quote_user_colour` INT(10) UNSIGNED    NOT NULL    DEFAULT '1073741824',
            `quote_timestamp`   TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
            `quote_text`        TEXT                NOT NULL,
            PRIMARY KEY (`quote_id`),
            INDEX `msz_chat_quotes_parent`          (`quote_parent`),
            INDEX `msz_chat_quotes_user_id_foreign` (`quote_user_id`),
            CONSTRAINT `msz_chat_quotes_user_id_foreign`
                FOREIGN KEY (`quote_user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE SET NULL
        )
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec('DROP TABLE `msz_chat_quotes`');
}
