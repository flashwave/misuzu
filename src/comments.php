<?php
use Misuzu\Database;

define('MSZ_COMMENTS_PERM_CREATE', 1);
define('MSZ_COMMENTS_PERM_EDIT_OWN', 1 << 1);
define('MSZ_COMMENTS_PERM_EDIT_ANY', 1 << 2);
define('MSZ_COMMENTS_PERM_DELETE_OWN', 1 << 3);
define('MSZ_COMMENTS_PERM_DELETE_ANY', 1 << 4);
define('MSZ_COMMENTS_PERM_PIN', 1 << 5);
define('MSZ_COMMENTS_PERM_LOCK', 1 << 6);

function comments_category_create(string $name): int
{
    $create = Database::prepare('
        INSERT INTO `msz_comments_categories`
            (`category_name`)
        VALUES
            (:name)
    ');
    $create->bindValue('name', $name);
    return $create->execute() ? Database::lastInsertId() : 0;
}

function comments_category_lock(int $category, bool $lock): void
{
    $lock = Database::prepare('
        UPDATE `msz_comments_categories`
        SET `category_locked` = IF(:lock, NOW(), NULL)
        WHERE `category_id` = :category
    ');
    $lock->bindValue('category', $category);
    $lock->bindValue('lock', $lock ? 1 : 0);
    $lock->execute();
}

function comments_category_exists(string $name): bool
{
    $exists = Database::prepare('
        SELECT COUNT(`category_name`) > 0
        FROM `msz_comments_categories`
        WHERE `category_name` = :name
    ');
    $exists->bindValue('name', $name);
    return $exists->execute() ? (bool)$exists->fetchColumn() : false;
}

function comments_category_get(int $category): array
{
    $posts = Database::prepare('
        SELECT
            p.`comment_id`, p.`comment_text`,
            u.`user_id`, u.`username`,
            COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`
        FROM `msz_comments_posts` as p
        LEFT JOIN `msz_users` as u
        ON u.`user_id` = p.`user_id`
        LEFT JOIN `msz_roles` as r
        ON r.`role_id` = u.`display_role`
        WHERE c.`category_id` = :category
    ');
    $posts->bindValue('category', $category);
    return $posts->execute() ? $posts->fetchAll(PDO::FETCH_ASSOC) : [];
}

function comments_post_create(int $user, int $category, string $text): int
{
    $create = Database::prepare('
        INSERT INTO `msz_comments_posts`
            (`user_id`, `category_id`, `comment_text`)
        VALUES
            (:user, :category, :text)
    ');
    $create->bindValue('user', $user);
    $create->bindValue('category', $category);
    $create->bindValue('text', $text);
    return $create->execute() ? Database::lastInsertId() : 0;
}
