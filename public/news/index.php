<?php
require_once '../../misuzu.php';

$categories = news_categories_get(0, 0, true);

$newsPagination = pagination_create(news_posts_count(null, true), 5);
$postsOffset = pagination_offset($newsPagination, pagination_param('page'));

if(!pagination_is_valid_offset($postsOffset)) {
    echo render_error(404);
    return;
}

$posts = news_posts_get(
    $postsOffset,
    $newsPagination['range'],
    null,
    true
);

if(!$posts) {
    echo render_error(404);
    return;
}

echo tpl_render('news.index', [
    'categories' => $categories,
    'posts' => $posts,
    'news_pagination' => $newsPagination,
]);
