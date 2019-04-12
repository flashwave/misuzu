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

if ($postId > 0) {
    $post = news_post_get($postId);

    if (!$post) {
        echo render_error(404);
        return;
    }

    if ($post['comment_section_id'] === null) {
        $commentsInfo = comments_category_create("news-{$post['post_id']}");

        if ($commentsInfo) {
            $post['comment_section_id'] = $commentsInfo['category_id'];
            news_post_comments_set(
                $post['post_id'],
                $post['comment_section_id'] = $commentsInfo['category_id']
            );
        }
    } else {
        $commentsInfo = comments_category_info($post['comment_section_id']);
    }

    echo tpl_render('news.post', [
        'post' => $post,
        'comments_perms' => comments_get_perms(user_session_current('user_id', 0)),
        'comments_category' => $commentsInfo,
        'comments' => comments_category_get($commentsInfo['category_id'], user_session_current('user_id', 0)),
    ]);
    return;
}

if ($categoryId > 0) {
    $category = news_category_get($categoryId, true);

    if (empty($category)) {
        echo render_error(404);
        return;
    }

    $categoryPagination = pagination_create($category['posts_count'], 5);
    $postsOffset = pagination_offset($categoryPagination, pagination_param('page'));

    if (!pagination_is_valid_offset($postsOffset)) {
        echo render_error(404);
        return;
    }

    $posts = news_posts_get(
        $postsOffset,
        $categoryPagination['range'],
        $category['category_id']
    );

    $featured = news_posts_get(0, 10, $category['category_id'], true);

    if ($feedMode === 'rss' || $feedMode === 'atom') {
        header("Content-Type: application/{$feedMode}+xml; charset=utf-8");
        echo news_feed($feedMode, $posts, [
            'title' => config_get('Site', 'name') . ' » ' . $category['category_name'],
            'subtitle' => $category['category_description'],
            'html-url' => url('news-category', ['category' => $category['category_id']]),
            'feed-url' => url("news-category-feed-{$feedMode}", ['category' => $category['category_id']]),
        ]);
        return;
    }

    echo tpl_render('news.category', [
        'category' => $category,
        'posts' => $posts,
        'featured' => $featured,
        'news_pagination' => $categoryPagination,
    ]);
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

if ($feedMode === 'rss' || $feedMode === 'atom') {
    header("Content-Type: application/{$feedMode}+xml; charset=utf-8");
    echo news_feed($feedMode, $posts, [
        'title' => config_get('Site', 'name') . ' » Featured News',
        'subtitle' => 'A live featured news feed.',
        'html-url' => url('news-index'),
        'feed-url' => url("news-feed-{$feedMode}"),
    ]);
    return;
}

echo tpl_render('news.index', [
    'categories' => $categories,
    'posts' => $posts,
    'news_pagination' => $newsPagination,
]);
