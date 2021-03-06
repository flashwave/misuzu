<?php
namespace Misuzu;

use Misuzu\News\NewsPost;
use Misuzu\Users\User;

require_once '../misuzu.php';

$searchQuery = !empty($_GET['q']) && is_string($_GET['q']) ? $_GET['q'] : '';

if(!empty($searchQuery)) {
    $forumTopics = forum_topic_listing_search($searchQuery, User::hasCurrent() ? User::getCurrent()->getId() : 0);
    $forumPosts = forum_post_search($searchQuery);
    $newsPosts = NewsPost::bySearchQuery($searchQuery);

    $findUsers = DB::prepare(sprintf(
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
        \Misuzu\Users\UserRelation::TYPE_FOLLOW
    ));
    $findUsers->bind('query', $searchQuery);
    $findUsers->bind('current_user_id', User::hasCurrent() ? User::getCurrent()->getId() : 0);
    $users = $findUsers->fetchAll();
}

Template::render('home.search', [
    'search_query' => $searchQuery,
    'forum_topics' => $forumTopics ?? [],
    'forum_posts' => $forumPosts ?? [],
    'users' => $users ?? [],
    'news_posts' => $newsPosts ?? [],
]);
