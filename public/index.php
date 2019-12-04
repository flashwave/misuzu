<?php
namespace Misuzu;

require_once '../misuzu.php';

$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if($requestPath !== '/' && $requestPath !== $_SERVER['PHP_SELF']) {
    echo render_error(404);
    return;
}

if(Config::get('social.embed_linked', Config::TYPE_BOOL)) {
    tpl_var('linked_data', [
        'name' => Config::get('site.name', Config::TYPE_STR, 'Misuzu'),
        'url' => Config::get('site.url', Config::TYPE_STR),
        'logo' => Config::get('site.ext_logo', Config::TYPE_STR),
        'same_as' => Config::get('social.linked', Config::TYPE_ARR),
    ]);
}

$news = news_posts_get(0, 5, null, true);

$stats = DB::query('
    SELECT
    (
        SELECT COUNT(`user_id`)
        FROM `msz_users`
        WHERE `user_deleted` IS NULL
    ) AS `count_users_all`,
    (
        SELECT COUNT(`user_id`)
        FROM `msz_users`
        WHERE `user_active` >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ) AS `count_users_online`,
    (
        SELECT COUNT(`user_id`)
        FROM `msz_users`
        WHERE `user_active` >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ) AS `count_users_active`,
    (
        SELECT COUNT(`comment_id`)
        FROM `msz_comments_posts`
        WHERE `comment_deleted` IS NULL
    ) AS `count_comments`,
    (
        SELECT COUNT(`topic_id`)
        FROM `msz_forum_topics`
        WHERE `topic_deleted` IS NULL
    ) AS `count_forum_topics`,
    (
        SELECT COUNT(`post_id`)
        FROM `msz_forum_posts`
        WHERE `post_deleted` IS NULL
    ) AS `count_forum_posts`
')->fetch();

$changelog = DB::query('
    SELECT
        `change_id`, `change_log`, `change_action`,
        DATE(`change_created`) AS `change_date`,
        !ISNULL(`change_text`) AS `change_has_text`
    FROM `msz_changelog_changes`
    ORDER BY `change_created` DESC
    LIMIT 10
')->fetchAll();

$birthdays = user_session_active() ? user_get_birthdays() : [];

$latestUser = DB::query('
    SELECT
        u.`user_id`, u.`username`, u.`user_created`,
        COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`
    FROM `msz_users` as u
    LEFT JOIN `msz_roles` as r
    ON r.`role_id` = u.`display_role`
    WHERE `user_deleted` IS NULL
    ORDER BY u.`user_id` DESC
    LIMIT 1
')->fetch();

$onlineUsers = DB::query('
    SELECT
        u.`user_id`, u.`username`,
        COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`
    FROM `msz_users` as u
    LEFT JOIN `msz_roles` as r
    ON r.`role_id` = u.`display_role`
    WHERE u.`user_active` >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ORDER BY u.`user_active` DESC
    LIMIT 104
')->fetchAll();

tpl_vars([
    'statistics' => $stats,
    'latest_user' => $latestUser,
    'online_users' => $onlineUsers,
    'birthdays' => $birthdays,
    'featured_changelog' => $changelog,
    'featured_news' => $news,
]);

echo tpl_render('home.landing');
