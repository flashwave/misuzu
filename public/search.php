<?php
require_once '../misuzu.php';

$searchQuery = !empty($_GET['q']) && is_string($_GET['q']) ? $_GET['q'] : '';

if (!empty($searchQuery)) {
    $forumTopics = forum_topic_listing_search($searchQuery, user_session_current('user_id', 0));

    $findForumPosts = db_prepare('
        SELECT fp.`post_id`, fp.`post_text`, ft.`topic_title`, u.`username`
        FROM `msz_forum_posts` AS fp
        LEFT JOIN `msz_forum_topics` AS ft
        ON ft.`topic_id` = fp.`topic_id`
        LEFT JOIN `msz_users` AS u
        ON u.`user_id` = fp.`user_id`
        WHERE MATCH(fp.`post_text`)
        AGAINST (:query IN NATURAL LANGUAGE MODE);
    ');
    $findForumPosts->bindValue('query', $searchQuery);
    $forumPosts = db_fetch_all($findForumPosts);

    $findUsers = db_prepare(sprintf(
        '
            SELECT
                :current_user_id AS `current_user_id`,
                u.`user_id`, u.`username`, u.`user_country`,
                u.`user_created`, u.`user_active`, r.`role_id`,
                COALESCE(u.`user_title`, r.`role_title`) AS `user_title`,
                COALESCE(u.`user_colour`, r.`role_colour`) AS `user_colour`,
                (
                    SELECT COUNT(`topic_id`)
                    FROM `msz_forum_topics`
                    WHERE `user_id` = u.`user_id`
                    AND `topic_deleted` IS NULL
                ) AS `user_count_topics`,
                (
                    SELECT COUNT(`post_Id`)
                    FROM `msz_forum_posts`
                    WHERE `user_id` = u.`user_id`
                    AND `post_deleted` IS NULL
                ) AS `user_count_posts`,
                (
                    SELECT COUNT(`subject_id`)
                    FROM `msz_user_relations`
                    WHERE `user_id` = u.`user_id`
                    AND `relation_type` = %1$d
                ) AS `user_count_following`,
                (
                    SELECT COUNT(`user_id`)
                    FROM `msz_user_relations`
                    WHERE `subject_id` = u.`user_id`
                    AND `relation_type` = %1$d
                ) AS `user_count_followers`,
                (
                    SELECT `relation_type` = %1$d
                    FROM `msz_user_relations`
                    WHERE `user_id` = `current_user_id`
                    AND `subject_id` = u.`user_id`
                ) AS `user_is_following`,
                (
                    SELECT `relation_type` = %1$d
                    FROM `msz_user_relations`
                    WHERE `user_id` = u.`user_id`
                    AND `subject_id` = `current_user_id`
                ) AS `user_is_follower`
            FROM `msz_users` AS u
            LEFT JOIN `msz_roles` AS r
            ON r.`role_id` = u.`display_role`
            LEFT JOIN `msz_user_roles` AS ur
            ON ur.`user_id` = u.`user_id`
            WHERE LOWER(u.`username`) LIKE CONCAT("%%", LOWER(:query), "%%")
            GROUP BY u.`user_id`
        ',
        MSZ_USER_RELATION_FOLLOW
    ));
    $findUsers->bindValue('query', $searchQuery);
    $findUsers->bindValue('current_user_id', user_session_current('user_id', 0));
    $users = db_fetch_all($findUsers);

    $findNewsPosts = db_prepare('
        SELECT `post_id`, `post_title`, `post_text`
        FROM `msz_news_posts`
        WHERE MATCH(`post_title`, `post_text`)
        AGAINST (:query IN NATURAL LANGUAGE MODE);
    ');
    $findNewsPosts->bindValue('query', $searchQuery);
    $newsPosts = db_fetch_all($findNewsPosts);
}

echo tpl_render('home.search', [
    'search_query' => $searchQuery,
    'forum_topics' => $forumTopics ?? [],
    'forum_posts' => $forumPosts ?? [],
    'users' => $users ?? [],
    'news_posts' => $newsPosts ?? [],
]);
