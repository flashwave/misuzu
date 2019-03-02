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

$stats = [
    'users' => db_query('
        SELECT
            (
                SELECT COUNT(`user_id`)
                FROM `msz_users`
                WHERE `user_deleted` IS NULL
            ) as `all`,
            (
                SELECT COUNT(`user_id`)
                FROM `msz_users`
                WHERE `user_active` >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ) as `online`,
            (
                SELECT COUNT(`user_id`)
                FROM `msz_users`
                WHERE `user_active` >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ) as `active`
    ')->fetch(PDO::FETCH_ASSOC),
    'comments' => (int)db_query('
        SELECT COUNT(`comment_id`)
        FROM `msz_comments_posts`
        WHERE `comment_deleted` IS NULL
    ')->fetchColumn(),
    'forum_topics' => (int)db_query('
        SELECT COUNT(`topic_id`)
        FROM `msz_forum_topics`
        WHERE `topic_deleted` IS NULL
    ')->fetchColumn(),
    'forum_posts' => (int)db_query('
        SELECT COUNT(`post_id`)
        FROM `msz_forum_posts`
        WHERE `post_deleted` IS NULL
    ')->fetchColumn(),
];

$changelog = db_query('
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

$birthdays = user_session_active() ? user_get_birthdays() : [];

$latestUser = db_query('
    SELECT
        u.`user_id`, u.`username`, u.`user_created`,
        COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`
    FROM `msz_users` as u
    LEFT JOIN `msz_roles` as r
    ON r.`role_id` = u.`display_role`
    WHERE `user_deleted` IS NULL
    ORDER BY u.`user_id` DESC
    LIMIT 1
')->fetch(PDO::FETCH_ASSOC);

$onlineUsers = db_query('
    SELECT
        u.`user_id`, u.`username`,
        COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`
    FROM `msz_users` as u
    LEFT JOIN `msz_roles` as r
    ON r.`role_id` = u.`display_role`
    WHERE u.`user_active` >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ORDER BY u.`user_active` DESC
    LIMIT 104
')->fetchAll(PDO::FETCH_ASSOC);

echo tpl_render('home.' . (user_session_active() ? 'home' : 'landing'), [
    'statistics' => $stats,
    'latest_user' => $latestUser,
    'online_users' => $onlineUsers,
    'birthdays' => $birthdays,
    'featured_changelog' => $changelog,
    'featured_news' => $news,
]);
