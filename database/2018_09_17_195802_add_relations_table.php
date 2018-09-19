<?php
namespace Misuzu\DatabaseMigrations\AddRelationsTable;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec('
        CREATE TABLE `msz_user_relations` (
            `user_id`           INT(10)     UNSIGNED    NOT NULL,
            `subject_id`        INT(10)     UNSIGNED    NOT NULL,
            `relation_type`     TINYINT(3)  UNSIGNED    NOT NULL,
            `relation_created`  TIMESTAMP               NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE  INDEX `user_relations_unique`               (`user_id`, `subject_id`),
                    INDEX `user_relations_subject_id_foreign`   (`subject_id`),
            CONSTRAINT `user_relations_subject_id_foreign`
                FOREIGN KEY (`subject_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            CONSTRAINT `user_relations_user_id_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        )
    ');
}

function migrate_down(PDO $conn): void
{
    $conn->exec('DROP TABLE `msz_user_relations`');
}
