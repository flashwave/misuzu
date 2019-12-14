<?php
namespace Misuzu\DatabaseMigrations\AddedChatTokensTable;

use PDO;

function migrate_up(PDO $conn): void {
    $conn->exec("
        CREATE TABLE `msz_user_chat_tokens` (
            `user_id`       INT(10) UNSIGNED NOT NULL,
            `token_string`  CHAR(64) NOT NULL,
            `token_created` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
            UNIQUE INDEX `user_chat_token_string_unique` (`token_string`),
                   INDEX `user_chat_token_user_foreign`  (`user_id`),
                   INDEX `user_chat_token_created_key`   (`token_created`),
            CONSTRAINT `user_chat_token_user_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");
}

function migrate_down(PDO $conn): void {
    $conn->exec("DROP TABLE `msz_user_chat_tokens`");
}
