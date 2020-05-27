<?php
namespace Misuzu\DatabaseMigrations\AddMissingIndexes;

use PDO;

function migrate_up(PDO $conn): void {
    $conn->exec("
        ALTER TABLE `msz_forum_categories`
            ADD INDEX `forum_link_clicks_index` (`forum_link_clicks`),
            ADD INDEX `forum_hidden_index` (`forum_hidden`);
    ");

    $conn->exec("
        ALTER TABLE `msz_login_attempts`
            ADD INDEX `login_attempts_success_index` (`attempt_success`),
            ADD INDEX `login_attempts_ip_index` (`attempt_ip`);
    ");

    $conn->exec("
        ALTER TABLE `msz_news_categories`
            ADD INDEX `news_categories_is_hidden_index` (`category_is_hidden`);
    ");

    $conn->exec("
        ALTER TABLE `msz_roles`
            ADD INDEX `roles_hierarchy_index` (`role_hierarchy`),
            ADD INDEX `roles_hidden_index` (`role_hidden`);
    ");

    $conn->exec("
        ALTER TABLE `msz_user_relations`
            ADD INDEX `user_relations_type_index` (`relation_type`),
            ADD INDEX `user_relations_created_index` (`relation_created`);
    ");
}

function migrate_down(PDO $conn): void {
    $conn->exec("
        ALTER TABLE `msz_forum_categories`
            DROP INDEX `forum_link_clicks_index`,
            DROP INDEX `forum_hidden_index`;
    ");

    $conn->exec("
        ALTER TABLE `msz_login_attempts`
            DROP INDEX `login_attempts_success_index`,
            DROP INDEX `login_attempts_ip_index`;
    ");

    $conn->exec("
        ALTER TABLE `msz_news_categories`
            DROP INDEX `news_categories_is_hidden_index`;
    ");

    $conn->exec("
        ALTER TABLE `msz_roles`
            DROP INDEX `roles_hierarchy_index`,
            DROP INDEX `roles_hidden_index`;
    ");

    $conn->exec("
        ALTER TABLE `msz_user_relations`
            DROP INDEX `user_relations_type_index`,
            DROP INDEX `user_relations_created_index`;
    ");
}
