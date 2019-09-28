<?php
namespace Misuzu;

require_once '../misuzu.php';

http_response_code(301);
$location = url('news-index');

if(!empty($_GET['n']) && is_string($_GET['n'])) {
    $location = url('news-post', [
        'post' => (int)$_GET['n'],
    ]);
}

$feedMode = trim($_SERVER['PATH_INFO'] ?? '', '/');
$categoryId = !empty($_GET['c']) && is_string($_GET['c']) ? (int)$_GET['c'] : 0;
$postId = !empty($_GET['p']) && is_string($_GET['p']) ? (int)$_GET['p'] : 0;

if(!empty($feedMode) && news_feed_supported($feedMode)) {
    $location = empty($categoryId) ? url("news-feed-{$feedMode}") : url("news-category-feed-{$feedMode}", ['category' => $categoryId]);
}

if($postId > 0) {
    $location = url('news-post', ['post' => $postId]);
}

if($categoryId > 0) {
    $location = url('news-category', ['category' => $categoryId, 'page' => pagination_param('page')]);
}

redirect($location);
