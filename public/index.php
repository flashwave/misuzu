<?php
require_once '../misuzu.php';

$showActivityFeed = user_session_active()
    && MSZ_DEBUG /*perms_check_user(MSZ_PERMS_GENERAL, user_session_current('user_id'), MSZ_PERM_GENERAL_TESTER)*/;

if ($showActivityFeed) {
    // load activity shit garbage here
} else {
    if (config_get_default(false, 'Site', 'embed_linked_data')) {
        tpl_var('linked_data', [
            'name' => config_get('Site', 'name'),
            'url' => config_get('Site', 'url'),
            'logo' => config_get('Site', 'external_logo'),
            'same_as' => explode(',', config_get_default('', 'Site', 'social_media')),
        ]);
    }

    $news = news_posts_get(0, 5, null, true);

    $stats = db_fetch(db_query('
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
    '));

    $changelog = db_fetch_all(db_query('
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
    '));

    $birthdays = user_session_active() ? user_get_birthdays() : [];

    $latestUser = db_fetch_all(db_query('
        SELECT
            u.`user_id`, u.`username`, u.`user_created`,
            COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`
        FROM `msz_users` as u
        LEFT JOIN `msz_roles` as r
        ON r.`role_id` = u.`display_role`
        WHERE `user_deleted` IS NULL
        ORDER BY u.`user_id` DESC
        LIMIT 1
    '));

    $onlineUsers = db_fetch_all(db_query('
        SELECT
            u.`user_id`, u.`username`,
            COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`
        FROM `msz_users` as u
        LEFT JOIN `msz_roles` as r
        ON r.`role_id` = u.`display_role`
        WHERE u.`user_active` >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ORDER BY u.`user_active` DESC
        LIMIT 104
    '));

    tpl_vars([
        'statistics' => $stats,
        'latest_user' => $latestUser,
        'online_users' => $onlineUsers,
        'birthdays' => $birthdays,
        'featured_changelog' => $changelog,
        'featured_news' => $news,
    ]);
}

echo tpl_render($showActivityFeed ? 'home.index' : 'home.landing');
