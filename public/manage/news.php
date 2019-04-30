<?php
require_once '../../misuzu.php';

$newsPerms = perms_get_user(user_session_current('user_id', 0))[MSZ_PERMS_NEWS];

switch ($_GET['v'] ?? null) {
    default:
    case 'posts':
        if (!perms_check($newsPerms, MSZ_PERM_NEWS_MANAGE_POSTS)) {
            echo render_error(403);
            break;
        }

        $postsPagination = pagination_create(news_posts_count(null, false, true, false), 15);
        $postsOffset = pagination_offset($postsPagination, pagination_param());

        if (!pagination_is_valid_offset($postsOffset)) {
            echo render_error(404);
            break;
        }

        $posts = news_posts_get($postsOffset, $postsPagination['range'], null, false, true, false);

        echo tpl_render('manage.news.posts', [
            'news_posts' => $posts,
            'posts_pagination' => $postsPagination,
        ]);
        break;

    case 'categories':
        if (!perms_check($newsPerms, MSZ_PERM_NEWS_MANAGE_CATEGORIES)) {
            echo render_error(403);
            break;
        }

        $categoriesPagination = pagination_create(news_categories_count(true), 15);
        $categoriesOffset = pagination_offset($categoriesPagination, pagination_param());

        if (!pagination_is_valid_offset($categoriesOffset)) {
            echo render_error(404);
            break;
        }

        $categories = news_categories_get($categoriesOffset, $categoriesPagination['range'], true, false, true);

        echo tpl_render('manage.news.categories', [
            'news_categories' => $categories,
            'categories_pagination' => $categoriesPagination,
        ]);
        break;

    case 'category':
        $category = [];
        $categoryId = (int)($_GET['c'] ?? null);

        if (!empty($_POST['category']) && csrf_verify('news_category', $_POST['csrf'] ?? '')) {
            $originalCategoryId = (int)($_POST['category']['id'] ?? null);
            $categoryId = news_category_create(
                $_POST['category']['name'] ?? null,
                $_POST['category']['description'] ?? null,
                !empty($_POST['category']['hidden']),
                $originalCategoryId
            );

            audit_log(
                $originalCategoryId === $categoryId
                    ? MSZ_AUDIT_NEWS_CATEGORY_EDIT
                    : MSZ_AUDIT_NEWS_CATEGORY_CREATE,
                user_session_current('user_id'),
                [$categoryId]
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
            $originalPostId = (int)($_POST['post']['id'] ?? null);
            $currentUserId = user_session_current('user_id');
            $title = $_POST['post']['title'] ?? null;
            $isFeatured = !empty($_POST['post']['featured']);
            $postId = news_post_create(
                $title,
                $_POST['post']['text'] ?? null,
                (int)($_POST['post']['category'] ?? null),
                user_session_current('user_id'),
                $isFeatured,
                null,
                $originalPostId
            );
            audit_log(
                $originalPostId === $postId
                    ? MSZ_AUDIT_NEWS_POST_EDIT
                    : MSZ_AUDIT_NEWS_POST_CREATE,
                $currentUserId,
                [$postId]
            );

            if (!$originalPostId && $isFeatured) {
                $twitterApiKey = config_get('Twitter', 'api_key');
                $twitterApiSecret = config_get('Twitter', 'api_secret');
                $twitterToken = config_get('Twitter', 'token');
                $twitterTokenSecret = config_get('Twitter', 'token_secret');

                if (!empty($twitterApiKey) && !empty($twitterApiSecret)
                    && !empty($twitterToken) && !empty($twitterTokenSecret)) {
                    twitter_init($twitterApiKey, $twitterApiSecret, $twitterToken, $twitterTokenSecret);
                    $url = url('news-post', ['post' => $postId]);
                    twitter_tweet_post("News :: {$title}\nhttps://{$_SERVER['HTTP_HOST']}{$url}");
                }
            }
        }

        if ($postId > 0) {
            $post = news_post_get($postId);
        }

        echo tpl_render('manage.news.post', compact('post', 'categories'));
        break;
}
