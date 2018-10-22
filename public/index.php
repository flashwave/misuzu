<?php
require_once '../misuzu.php';

if (config_get_default(false, 'Site', 'embed_linked_data')) {
    tpl_var('linked_data', [
        'name' => config_get('Site', 'name'),
        'url' => config_get('Site', 'url'),
        'logo' => config_get('Site', 'external_logo'),
        'same_as' => explode(',', config_get_default('', 'Site', 'social_media')),
    ]);
}

$news = news_posts_get(0, 5, null, true);

$statistics = cache_get('index:stats:v1', function () {
    return [
        'users' => (int)db_query('
            SELECT COUNT(`user_id`)
            FROM `msz_users`
        ')->fetchColumn(),
        'lastUser' => db_query('
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

$changelog = cache_get('index:changelog:v1', function () {
    return db_query('
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

$onlineUsers = cache_get('index:online:v1', function () {
    return db_query('
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
    'chat_quote' => chat_quotes_random(),
    'featured_changelog' => $changelog,
    'featured_news' => $news,
]);
