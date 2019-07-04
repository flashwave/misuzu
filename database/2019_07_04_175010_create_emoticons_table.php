<?php
namespace Misuzu\DatabaseMigrations\CreateEmoticonsTable;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        CREATE TABLE `msz_emoticons` (
            `emote_id`          INT(10) UNSIGNED    NOT NULL AUTO_INCREMENT,
            `emote_order`       MEDIUMINT(9)        NOT NULL DEFAULT 0,
            `emote_hierarchy`   INT(11)             NOT NULL DEFAULT 0,
            `emote_string`      VARCHAR(50)         NOT NULL COLLATE 'ascii_general_nopad_ci',
            `emote_url`         VARCHAR(255)        NOT NULL COLLATE 'utf8mb4_bin',
            PRIMARY KEY (`emote_id`),
            UNIQUE  INDEX `emotes_string`       (`emote_string`),
                    INDEX `emotes_order`        (`emote_order`),
                    INDEX `emotes_hierarchy`    (`emote_hierarchy`),
                    INDEX `emotes_url`          (`emote_url`)
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec("
        DROP TABLE `msz_emoticons`;
    ");
}
