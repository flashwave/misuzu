<?php
namespace Misuzu;

require_once '../../misuzu.php';

$categoryId = !empty($_GET['c']) && is_string($_GET['c']) ? (int)$_GET['c'] : 0;
$category = news_category_get($categoryId, true);

if(empty($category)) {
    echo render_error(404);
    return;
}

$categoryPagination = pagination_create($category['posts_count'], 5);
$postsOffset = pagination_offset($categoryPagination, pagination_param());

if(!pagination_is_valid_offset($postsOffset)) {
    echo render_error(404);
    return;
}

$posts = news_posts_get(
    $postsOffset,
    $categoryPagination['range'],
    $category['category_id']
);

$featured = news_posts_get(0, 10, $category['category_id'], true);

echo tpl_render('news.category', [
    'category' => $category,
    'posts' => $posts,
    'featured' => $featured,
    'news_pagination' => $categoryPagination,
]);
