<?php
require_once '../../misuzu.php';

$feedMode = trim($_SERVER['PATH_INFO'] ?? '', '/');

if(!news_feed_supported($feedMode)) {
    echo render_error(400);
    return;
}

$categoryId = !empty($_GET['c']) && is_string($_GET['c']) ? (int)$_GET['c'] : 0;

if(!empty($categoryId)) {
    $category = news_category_get($categoryId);

    if (empty($category)) {
        echo render_error(404);
        return;
    }
}

$posts = news_posts_get(0, 10, $category['category_id'] ?? null, empty($category));

if (!$posts) {
    echo render_error(404);
    return;
}

header("Content-Type: application/{$feedMode}+xml; charset=utf-8");

echo news_feed($feedMode, $posts, [
    'title' => config_get('Site', 'name') . ' » ' . ($category['category_name'] ?? 'Featured News'),
    'subtitle' => $category['category_description'] ?? 'A live featured news feed.',
    'html-url' => empty($category) ? url('news-index') : url('news-category', ['category' => $category['category_id']]),
    'feed-url' => empty($category) ? url("news-feed-{$feedMode}") : url("news-category-feed-{$feedMode}", ['category' => $category['category_id']]),
]);
