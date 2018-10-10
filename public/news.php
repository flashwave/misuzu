<?php
require_once '../misuzu.php';

$categoryId = isset($_GET['c']) ? (int)$_GET['c'] : null;
$postId = isset($_GET['p']) ? (int)$_GET['p'] : (isset($_GET['n']) ? (int)$_GET['n'] : null);
$postsOffset = (int)($_GET['o'] ?? 0);
$postsTake = 5;

tpl_vars([
    'posts_offset' => $postsOffset,
    'posts_take' => $postsTake,
]);

if ($postId !== null) {
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

if ($categoryId !== null) {
    $category = news_categories_single($categoryId, true);

    if (!$category || $postsOffset < 0 || $postsOffset >= $category['posts_count']) {
        echo render_error(404);
        return;
    }

    $posts = news_posts_get(
        $postsOffset,
        $postsTake,
        $category['category_id']
    );

    $featured = news_posts_get(0, 10, $category['category_id'], true);

    echo tpl_render('news.category', compact('category', 'posts', 'featured'));
    return;
}

$categories = news_categories_get(0, 0, true);
$postsCount = news_posts_count(null, true);

tpl_var('posts_count', $postsCount);

if ($postsOffset < 0 || $postsOffset >= $postsCount) {
    echo render_error(404);
    return;
}

$posts = news_posts_get(
    $postsOffset,
    $postsTake,
    null,
    true
);

if (!$posts) {
    echo render_error(404);
    return;
}

echo tpl_render('news.index', compact('categories', 'posts'));
