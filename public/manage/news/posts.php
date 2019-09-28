<?php
namespace Misuzu;

require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_NEWS, user_session_current('user_id'), MSZ_PERM_NEWS_MANAGE_POSTS)) {
    echo render_error(403);
    return;
}

$postsPagination = pagination_create(news_posts_count(null, false, true, false), 15);
$postsOffset = pagination_offset($postsPagination, pagination_param());

if(!pagination_is_valid_offset($postsOffset)) {
    echo render_error(404);
    return;
}

$posts = news_posts_get($postsOffset, $postsPagination['range'], null, false, true, false);

echo tpl_render('manage.news.posts', [
    'news_posts' => $posts,
    'posts_pagination' => $postsPagination,
]);
