<?php
namespace Misuzu;

require_once '../../misuzu.php';

$categories = news_categories_get(0, 0, true);

$newsPagination = new Pagination(news_posts_count(null, true), 5, 'page');

if(!$newsPagination->hasValidOffset()) {
    echo render_error(404);
    return;
}

$posts = news_posts_get(
    $newsPagination->getOffset(),
    $newsPagination->getRange(),
    null,
    true
);

if(!$posts) {
    echo render_error(404);
    return;
}

Template::render('news.index', [
    'categories' => $categories,
    'posts' => $posts,
    'news_pagination' => $newsPagination,
]);
