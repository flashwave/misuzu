<?php
require_once '../../misuzu.php';

use_legacy_style();

$newsPerms = perms_get_user(MSZ_PERMS_NEWS, user_session_current('user_id', 0));

switch ($_GET['v'] ?? null) {
    default:
    case 'posts':
        if (!perms_check($newsPerms, MSZ_PERM_NEWS_MANAGE_POSTS)) {
            echo render_error(403);
            break;
        }

        $postTake = 15;
        $postOffset = (int)($_GET['o'] ?? 0);
        $posts = news_posts_get($postOffset, $postTake, null, false, true, false);
        $postsCount = news_posts_count(null, false, true, false);

        echo tpl_render('manage.news.posts', [
            'news_posts' => $posts,
            'posts_offset' => $postOffset,
            'posts_take' => $postTake,
            'posts_count' => $postsCount,
        ]);
        break;

    case 'categories':
        if (!perms_check($newsPerms, MSZ_PERM_NEWS_MANAGE_CATEGORIES)) {
            echo render_error(403);
            break;
        }

        $catTake = 15;
        $catOffset = (int)($_GET['o'] ?? 0);
        $categories = news_categories_get($catOffset, $catTake, true, false, true);
        $categoryCount = news_categories_count(true);

        echo tpl_render('manage.news.categories', [
            'news_categories' => $categories,
            'categories_offset' => $catOffset,
            'categories_take' => $catTake,
            'categories_count' => $categoryCount,
        ]);
        break;

    case 'category':
        $category = [];
        $categoryId = (int)($_GET['c'] ?? null);

        if (!empty($_POST['category']) && csrf_verify('news_category', $_POST['csrf'] ?? '')) {
            $categoryId = news_category_create(
                $_POST['category']['name'] ?? null,
                $_POST['category']['description'] ?? null,
                !empty($_POST['category']['hidden']),
                (int)($_POST['category']['id'] ?? null)
            );
        }

        if ($categoryId > 0) {
            $category = news_category_get($categoryId);
        }

        echo tpl_render('manage.news.category', compact('category'));
        break;

    case 'post':
        $post = [];
        $postId = (int)($_GET['p'] ?? null);
        $categories = news_categories_get(0, 0, false, false, true);

        if (!empty($_POST['post']) && csrf_verify('news_post', $_POST['csrf'] ?? '')) {
            $postId = news_post_create(
                $_POST['post']['title'] ?? null,
                $_POST['post']['text'] ?? null,
                (int)($_POST['post']['category'] ?? null),
                user_session_current('user_id'),
                !empty($_POST['post']['featured']),
                null,
                (int)($_POST['post']['id'] ?? null)
            );
        }

        if ($postId > 0) {
            $post = news_post_get($postId);
        }

        echo tpl_render('manage.news.post', compact('post', 'categories'));
        break;
}
