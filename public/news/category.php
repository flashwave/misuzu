<?php
namespace Misuzu;

require_once '../../misuzu.php';

$categoryId = !empty($_GET['c']) && is_string($_GET['c']) ? (int)$_GET['c'] : 0;
$category = news_category_get($categoryId, true);

if(empty($category)) {
    echo render_error(404);
    return;
}

$categoryPagination = new Pagination($category['posts_count'], 5);

if(!$categoryPagination->hasValidOffset()) {
    echo render_error(404);
    return;
}

$posts = news_posts_get(
    $categoryPagination->getOffset(),
    $categoryPagination->getRange(),
    $category['category_id']
);

$featured = news_posts_get(0, 10, $category['category_id'], true);

Template::render('news.category', [
    'category' => $category,
    'posts' => $posts,
    'featured' => $featured,
    'news_pagination' => $categoryPagination,
]);
