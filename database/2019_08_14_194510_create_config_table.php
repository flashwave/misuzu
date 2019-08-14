<?php
namespace Misuzu\DatabaseMigrations\CreateConfigTable;

use PDO;

function migrate_up(PDO $conn): void {
    $conn->exec("
        CREATE TABLE `msz_config` (
            `config_name`   VARCHAR(100)    NOT NULL COLLATE 'utf8mb4_bin',
            `config_value`  BLOB            NOT NULL DEFAULT '',
            PRIMARY KEY (`config_name`)
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");
}

function migrate_down(PDO $conn): void {
    $conn->exec("DROP TABLE `msz_config`;");
}
