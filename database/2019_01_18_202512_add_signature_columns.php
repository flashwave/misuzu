<?php
namespace Misuzu\DatabaseMigrations\AddSignatureColumns;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_users`
            ADD COLUMN `user_signature_content` TEXT NULL AFTER `user_about_parser`,
            ADD COLUMN `user_signature_parser` TINYINT(4) NOT NULL DEFAULT '0' AFTER `user_signature_content`;
    ");
    $conn->exec("
        ALTER TABLE `msz_forum_posts`
            ADD COLUMN `post_display_signature` TINYINT(4) UNSIGNED NOT NULL DEFAULT '1' AFTER `post_parse`;
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_forum_posts`
            DROP COLUMN `post_display_signature`;
    ");

    $conn->exec("
        ALTER TABLE `msz_users`
            DROP COLUMN `user_signature_content`,
            DROP COLUMN `user_signature_parser`;
    ");
}
