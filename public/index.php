<?php
use Misuzu\Application;
use Misuzu\Cache;
use Misuzu\Database;

require_once '../misuzu.php';

if (config_get_default(false, 'Site', 'embed_linked_data')) {
    tpl_var('linked_data', [
        'name' => config_get('Site', 'name'),
        'url' => config_get('Site', 'url'),
        'logo' => config_get('Site', 'external_logo'),
        'same_as' => explode(',', config_get_default('', 'Site', 'social_media')),
    ]);
}

$news = Database::query('
    SELECT
        p.`post_id`, p.`post_title`, p.`post_text`, p.`created_at`,
        u.`user_id`, u.`username`,
        COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`,
        (
            SELECT COUNT(`comment_id`)
            FROM `msz_comments_posts`
            WHERE `category_id` = `comment_section_id`
        ) as `post_comments`
    FROM `msz_news_posts` as p
    LEFT JOIN `msz_users` as u
    ON p.`user_id` = u.`user_id`
    LEFT JOIN `msz_roles` as r
    ON u.`display_role` = r.`role_id`
    WHERE p.`is_featured` = true
    ORDER BY p.`created_at` DESC
    LIMIT 5
')->fetchAll(PDO::FETCH_ASSOC);

$statistics = Cache::instance()->get('index:stats:v1', function () {
    return [
        'users' => (int)Database::query('
            SELECT COUNT(`user_id`)
            FROM `msz_users`
        ')->fetchColumn(),
        'lastUser' => Database::query('
            SELECT
                u.`user_id`, u.`username`, u.`created_at`,
                COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`
            FROM `msz_users` as u
            LEFT JOIN `msz_roles` as r
            ON r.`role_id` = u.`display_role`
            ORDER BY u.`user_id` DESC
            LIMIT 1
        ')->fetch(PDO::FETCH_ASSOC),
    ];
}, 10800);

$changelog = Cache::instance()->get('index:changelog:v1', function () {
    return Database::query('
        SELECT
            c.`change_id`, c.`change_log`,
            a.`action_name`, a.`action_colour`, a.`action_class`,
            DATE(`change_created`) as `change_date`,
            !ISNULL(c.`change_text`) as `change_has_text`
        FROM `msz_changelog_changes` as c
        LEFT JOIN `msz_changelog_actions` as a
        ON a.`action_id` = c.`action_id`
        ORDER BY c.`change_created` DESC
        LIMIT 10
    ')->fetchAll(PDO::FETCH_ASSOC);
}, 1800);

$onlineUsers = Cache::instance()->get('index:online:v1', function () {
    return Database::query('
        SELECT
            u.`user_id`, u.`username`,
            COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`
        FROM `msz_users` as u
        LEFT JOIN `msz_roles` as r
        ON r.`role_id` = u.`display_role`
        WHERE u.`last_seen` >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ORDER BY u.`last_seen` DESC
    ')->fetchAll(PDO::FETCH_ASSOC);
}, 30);

echo tpl_render('home.index', [
    'users_count' => $statistics['users'],
    'last_user' => $statistics['lastUser'],
    'online_users' => $onlineUsers,
    'featured_changelog' => $changelog,
    'featured_news' => $news,
]);
