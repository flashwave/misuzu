<?php
namespace Misuzu\DatabaseMigrations\AddMoreIndices;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_forum_posts`
            ADD INDEX `posts_parse_index` (`post_parse`),
            ADD INDEX `posts_edited_index` (`post_edited`),
            ADD INDEX `posts_display_signature_index` (`post_display_signature`),
            ADD INDEX `posts_ip_index` (`post_ip`),
            ADD FULLTEXT INDEX `posts_fulltext` (`post_text`);
    ");

    $conn->exec("
        ALTER TABLE `msz_comments_categories`
            ADD INDEX `comments_categories_locked_index` (`category_locked`);
    ");

    $conn->exec("
        ALTER TABLE `msz_forum_topics`
            DROP INDEX `topics_indices`,
            ADD FULLTEXT INDEX `topics_fulltext` (`topic_title`),
            ADD INDEX `topics_type_index` (`topic_type`),
            ADD INDEX `topics_created_index` (`topic_created`),
            ADD INDEX `topics_bumped_index` (`topic_bumped`),
            ADD INDEX `topics_deleted_index` (`topic_deleted`),
            ADD INDEX `topics_locked_index` (`topic_locked`);
    ");

    $conn->exec("
        ALTER TABLE `msz_news_posts`
            DROP INDEX `news_posts_indices`,
            ADD INDEX `news_posts_featured_index` (`post_is_featured`),
            ADD INDEX `news_posts_scheduled_index` (`post_scheduled`),
            ADD INDEX `news_posts_created_index` (`post_created`),
            ADD INDEX `news_posts_updated_index` (`post_updated`),
            ADD INDEX `news_posts_deleted_index` (`post_deleted`),
            ADD FULLTEXT INDEX `news_posts_fulltext` (`post_title`, `post_text`);
    ");

    $conn->exec("
        ALTER TABLE `msz_user_warnings`
            DROP INDEX `user_warnings_indices`,
            ADD INDEX `user_warnings_created_index` (`warning_created`),
            ADD INDEX `user_warnings_duration_index` (`warning_duration`),
            ADD INDEX `user_warnings_type_index` (`warning_type`),
            ADD INDEX `user_warnings_user_ip_index` (`user_ip`);
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_user_warnings`
            DROP INDEX `user_warnings_user_ip_index`,
            DROP INDEX `user_warnings_type_index`,
            DROP INDEX `user_warnings_duration_index`,
            DROP INDEX `user_warnings_created_index`,
            ADD INDEX `user_warnings_indices` (`warning_created`, `warning_type`, `warning_duration`, `user_ip`);
    ");

    $conn->exec("
        ALTER TABLE `msz_news_posts`
            DROP INDEX `news_posts_fulltext`,
            DROP INDEX `news_posts_deleted_index`,
            DROP INDEX `news_posts_created_index`,
            DROP INDEX `news_posts_updated_index`,
            DROP INDEX `news_posts_scheduled_index`,
            DROP INDEX `news_posts_featured_index`,
            ADD INDEX `news_posts_indices` (`post_is_featured`, `post_scheduled`, `post_created`);
    ");

    $conn->exec("
        ALTER TABLE `msz_forum_topics`
            DROP INDEX `topics_fulltext`,
            DROP INDEX `topics_locked_index`,
            DROP INDEX `topics_deleted_index`,
            DROP INDEX `topics_created_index`,
            DROP INDEX `topics_bumped_index`,
            DROP INDEX `topics_type_index`,
            ADD INDEX `topics_indices` (`topic_type`, `topic_bumped`);
    ");

    $conn->exec("
        ALTER TABLE `msz_comments_categories`
            DROP INDEX `comments_categories_locked_index`;
    ");

    $conn->exec("
        ALTER TABLE `msz_forum_posts`
            DROP INDEX `posts_fulltext`,
            DROP INDEX `posts_ip_index`,
            DROP INDEX `posts_display_signature_index`,
            DROP INDEX `posts_edited_index`,
            DROP INDEX `posts_parse_index`,;
    ");
}
