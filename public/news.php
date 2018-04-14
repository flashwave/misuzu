<?php
use Misuzu\News\NewsCategory;
use Misuzu\News\NewsPost;

require_once __DIR__ . '/../misuzu.php';

$category_id = empty($_GET['c']) ? null : (int)$_GET['c'];
$post_id = empty($_GET['n']) ? null : (int)$_GET['n'];

if ($post_id !== null) {
    $post = NewsPost::find($post_id);

    if ($post === null) {
        http_response_code(404);
        echo $app->templating->render('errors.404');
        return;
    }

    echo $app->templating->render('news.post', compact('post'));
    return;
}

if ($category_id !== null) {
    $category = NewsCategory::find($category_id);

    if ($category === null) {
        http_response_code(404);
        echo $app->templating->render('errors.404');
        return;
    }

    $posts = $category->posts()->orderBy('created_at', 'desc')->paginate(5);
    $featured = $category->where('is_featured', 1)->orderBy('created_at', 'desc')->take(10);
    echo $app->templating->render('news.category', compact('category', 'posts', 'featured'));
    return;
}

$categories = NewsCategory::where('is_hidden', false)->get();
$posts = NewsPost::where('is_featured', true)->paginate(5);

echo $app->templating->render('news.index', compact('categories', 'posts'));
