<?php
namespace Misuzu;

require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_NEWS, user_session_current('user_id'), MSZ_PERM_NEWS_MANAGE_POSTS)) {
    echo render_error(403);
    return;
}

$postsPagination = new Pagination(news_posts_count(null, false, true, false), 15);

if(!$postsPagination->hasValidOffset()) {
    echo render_error(404);
    return;
}

$posts = news_posts_get(
    $postsPagination->getOffset(),
    $postsPagination->getRange(),
    null, false, true, false
);

Template::render('manage.news.posts', [
    'news_posts' => $posts,
    'posts_pagination' => $postsPagination,
]);
