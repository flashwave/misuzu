<?php
require_once '../misuzu.php';

if (!empty($_GET['n']) && is_string($_GET['n'])) {
    header('Location: ' . url('news-post', [
        'post' => (int)$_GET['n'],
    ]));
    http_response_code(301);
    return;
}

$feedMode = trim($_SERVER['PATH_INFO'] ?? '', '/');
$categoryId = !empty($_GET['c']) && is_string($_GET['c']) ? (int)$_GET['c'] : 0;
$postId = !empty($_GET['p']) && is_string($_GET['p']) ? (int)$_GET['p'] : 0;

if(!empty($feedMode) && news_feed_supported($feedMode)) {
    http_response_code(301);
    header('Location: ' . (empty($categoryId) ? url("news-feed-{$feedMode}") : url("news-category-feed-{$feedMode}", ['category' => $categoryId])));
    return;
}

if ($postId > 0) {
    http_response_code(301);
    header('Location: ' . url('news-post', ['post' => $postId]));
    return;
}

if ($categoryId > 0) {
    http_response_code(301);
    header('Location: ' . url('news-category', ['category' => $categoryId, 'page' => pagination_param('page')]));
    return;
}

$categories = news_categories_get(0, 0, true);

$newsPagination = pagination_create(news_posts_count(null, true), 5);
$postsOffset = pagination_offset($newsPagination, pagination_param('page'));

if (!pagination_is_valid_offset($postsOffset)) {
    echo render_error(404);
    return;
}

$posts = news_posts_get(
    $postsOffset,
    $newsPagination['range'],
    null,
    true
);

if (!$posts) {
    echo render_error(404);
    return;
}

echo tpl_render('news.index', [
    'categories' => $categories,
    'posts' => $posts,
    'news_pagination' => $newsPagination,
]);
