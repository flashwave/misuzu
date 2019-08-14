<?php
require_once '../misuzu.php';

$showActivityFeed = false; /*user_session_active()
    && MSZ_DEBUG /*perms_check_user(MSZ_PERMS_GENERAL, user_session_current('user_id'), MSZ_PERM_GENERAL_TESTER)*/;

if($showActivityFeed) {
    // load activity shit garbage here
} else {
    if(config_get('social.embed_linked', MSZ_CFG_BOOL)) {
        tpl_var('linked_data', [
            'name' => config_get('site.name', MSZ_CFG_STR, 'Misuzu'),
            'url' => config_get('site.url', MSZ_CFG_STR),
            'logo' => config_get('site.ext_logo', MSZ_CFG_STR),
            'same_as' => config_get('social.linked', MSZ_CFG_ARR),
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
            `change_id`, `change_log`, `change_action`,
            DATE(`change_created`) AS `change_date`,
            !ISNULL(`change_text`) AS `change_has_text`
        FROM `msz_changelog_changes`
        ORDER BY `change_created` DESC
        LIMIT 10
    '));

    $birthdays = user_session_active() ? user_get_birthdays() : [];

    $latestUser = db_fetch(db_query('
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
