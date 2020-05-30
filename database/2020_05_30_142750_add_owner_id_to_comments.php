<?php
namespace Misuzu\DatabaseMigrations\AddOwnerIdToComments;

use PDO;

function migrate_up(PDO $conn): void {
    $conn->exec("
        ALTER TABLE `msz_comments_categories`
            ADD COLUMN `owner_id` INT UNSIGNED NULL DEFAULT NULL AFTER `category_name`,
            ADD INDEX `comments_categories_owner_foreign` (`owner_id`),
            ADD CONSTRAINT `comments_categories_owner_foreign`
                FOREIGN KEY (`owner_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE SET NULL;
    ");
}

function migrate_down(PDO $conn): void {
    $conn->exec("
        ALTER TABLE `msz_comments_categories`
            DROP COLUMN `owner_id`,
            DROP INDEX `comments_categories_owner_foreign`,
            DROP FOREIGN KEY `comments_categories_owner_foreign`;
    ");
}
