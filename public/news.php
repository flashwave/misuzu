<?php
use Misuzu\News\NewsCategory;
use Misuzu\News\NewsPost;

require_once __DIR__ . '/../misuzu.php';

$templating = $app->getTemplating();

$category_id = isset($_GET['c']) ? (int)$_GET['c'] : null;
$post_id = isset($_GET['n']) ? (int)$_GET['n'] : null;
$page_id = (int)($_GET['p'] ?? 1);

if ($post_id !== null) {
    $post = NewsPost::find($post_id);

    if ($post === null) {
        http_response_code(404);
        echo $templating->render('errors.404');
        return;
    }

    echo $templating->render('news.post', compact('post'));
    return;
}

if ($category_id !== null) {
    $category = NewsCategory::find($category_id);

    if ($category === null) {
        http_response_code(404);
        echo $templating->render('errors.404');
        return;
    }

    $posts = $category->posts()->orderBy('created_at', 'desc')->paginate(5, ['*'], 'p', $page_id);

    if (!is_valid_page($posts, $page_id)) {
        http_response_code(404);
        echo $templating->render('errors.404');
        return;
    }

    $featured = $category->posts()->where('is_featured', 1)->orderBy('created_at', 'desc')->take(10)->get();
    echo $templating->render('news.category', compact('category', 'posts', 'featured', 'page_id'));
    return;
}

$categories = NewsCategory::where('is_hidden', false)->get();
$posts = NewsPost::where('is_featured', true)->orderBy('created_at', 'desc')->paginate(5, ['*'], 'p', $page_id);

if (!is_valid_page($posts, $page_id)) {
    http_response_code(404);
    echo $templating->render('errors.404');
    return;
}

echo $templating->render('news.index', compact('categories', 'posts', 'page_id'));
