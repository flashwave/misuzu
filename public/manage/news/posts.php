<?php
namespace Misuzu;

use Misuzu\News\NewsPost;

require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_NEWS, user_session_current('user_id'), MSZ_PERM_NEWS_MANAGE_POSTS)) {
    echo render_error(403);
    return;
}

$postsPagination = new Pagination(NewsPost::countAll(false, true, true), 15);

if(!$postsPagination->hasValidOffset()) {
    echo render_error(404);
    return;
}

$posts = NewsPost::all($postsPagination, false, true, true);

Template::render('manage.news.posts', [
    'news_posts' => $posts,
    'posts_pagination' => $postsPagination,
]);
