<?php
require_once '../misuzu.php';

$searchQuery = !empty($_GET['q']) && is_string($_GET['q']) ? $_GET['q'] : '';

if (!empty($searchQuery)) {
    $findForumTopics = db_prepare('
        SELECT `topic_id`, `topic_title`
        FROM `msz_forum_topics`
        WHERE MATCH(`topic_title`)
        AGAINST (:query IN NATURAL LANGUAGE MODE);
    ');
    $findForumTopics->bindValue('query', $searchQuery);
    $forumTopics = db_fetch_all($findForumTopics);

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

    $findUsers = db_prepare('
        SELECT `user_id`, `username`
        FROM `msz_users`
        WHERE LOWER(`username`) LIKE CONCAT("%", LOWER(:query), "%");
    ');
    $findUsers->bindValue('query', $searchQuery);
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
