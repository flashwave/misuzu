<?php
use Misuzu\Database;

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
    $getPost = Database::prepare('
        SELECT
            p.`post_id`, p.`post_title`, p.`post_text`, p.`created_at`, p.`comment_section_id`,
            c.`category_id`, c.`category_name`,
            u.`user_id`, u.`username`,
            COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`
        FROM `msz_news_posts` as p
        LEFT JOIN `msz_news_categories` as c
        ON p.`category_id` = c.`category_id`
        LEFT JOIN `msz_users` as u
        ON p.`user_id` = u.`user_id`
        LEFT JOIN `msz_roles` as r
        ON u.`display_role` = r.`role_id`
        WHERE `post_id` = :post_id
    ');
    $getPost->bindValue(':post_id', $postId, PDO::PARAM_INT);
    $post = $getPost->execute() ? $getPost->fetch() : false;

    if ($post === false) {
        echo render_error(404);
        return;
    }

    if ($post['comment_section_id'] === null) {
        $commentsInfo = comments_category_create("news-{$post['post_id']}");

        if ($commentsInfo) {
            $post['comment_section_id'] = $commentsInfo['category_id'];
            Database::prepare('
                UPDATE `msz_news_posts`
                SET `comment_section_id` = :comment_section_id
                WHERE `post_id` = :post_id
            ')->execute([
                'comment_section_id' => $post['comment_section_id'],
                'post_id' => $post['post_id'],
            ]);
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
    $getCategory = Database::prepare('
        SELECT
            c.`category_id`, c.`category_name`, c.`category_description`,
            COUNT(p.`post_id`) AS `posts_count`
        FROM `msz_news_categories` as c
        LEFT JOIN `msz_news_posts` as p
        ON c.`category_id` = p.`category_id`
        WHERE c.`category_id` = :category_id
        GROUP BY c.`category_id`
    ');
    $getCategory->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
    $category = $getCategory->execute() ? $getCategory->fetch() : false;

    if ($category === false || $postsOffset < 0 || $postsOffset >= $category['posts_count']) {
        echo render_error(404);
        return;
    }

    $getPosts = Database::prepare('
        SELECT
            p.`post_id`, p.`post_title`, p.`post_text`, p.`created_at`,
            c.`category_id`, c.`category_name`,
            u.`user_id`, u.`username`,
            COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`,
            (
                SELECT COUNT(`comment_id`)
                FROM `msz_comments_posts`
                WHERE `category_id` = `comment_section_id`
            ) as `post_comments`
        FROM `msz_news_posts` as p
        LEFT JOIN `msz_news_categories` as c
        ON p.`category_id` = c.`category_id`
        LEFT JOIN `msz_users` as u
        ON p.`user_id` = u.`user_id`
        LEFT JOIN `msz_roles` as r
        ON u.`display_role` = r.`role_id`
        WHERE p.`category_id` = :category_id
        ORDER BY `created_at` DESC
        LIMIT :offset, :take
    ');
    $getPosts->bindValue('offset', $postsOffset);
    $getPosts->bindValue('take', $postsTake);
    $getPosts->bindValue('category_id', $category['category_id'], PDO::PARAM_INT);
    $posts = $getPosts->execute() ? $getPosts->fetchAll() : false;

    $getFeatured = Database::prepare('
        SELECT `post_id`, `post_title`
        FROM `msz_news_posts`
        WHERE `category_id` = :category_id
        AND `is_featured` = true
        ORDER BY `created_at` DESC
        LIMIT 10
    ');
    $getFeatured->bindValue('category_id', $category['category_id'], PDO::PARAM_INT);
    $featured = $getFeatured->execute() ? $getFeatured->fetchAll() : [];

    echo tpl_render('news.category', compact('category', 'posts', 'featured'));
    return;
}

$getCategories = Database::prepare('
    SELECT
        c.`category_id`, c.`category_name`,
        COUNT(p.`post_id`) AS count
    FROM `msz_news_categories` as c
    LEFT JOIN `msz_news_posts` as p
    ON c.`category_id` = p.`category_id`
    WHERE `is_hidden` = false
    GROUP BY c.`category_id`
    HAVING count > 0
');
$categories = $getCategories->execute() ? $getCategories->fetchAll() : [];

$postsCount = (int)Database::query('
    SELECT COUNT(p.`post_id`) as `posts_count`
    FROM `msz_news_posts` as p
    LEFT JOIN `msz_news_categories` as c
    ON p.`category_id` = c.`category_id`
    WHERE p.`is_featured` = true
    AND c.`is_hidden` = false
')->fetchColumn();

tpl_var('posts_count', $postsCount);

if ($postsOffset < 0 || $postsOffset >= $postsCount) {
    echo render_error(404);
    return;
}

$getPosts = Database::prepare('
    SELECT
        p.`post_id`, p.`post_title`, p.`post_text`, p.`created_at`,
        c.`category_id`, c.`category_name`,
        u.`user_id`, u.`username`,
        COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`,
        (
            SELECT COUNT(`comment_id`)
            FROM `msz_comments_posts`
            WHERE `category_id` = `comment_section_id`
        ) as `post_comments`
    FROM `msz_news_posts` as p
    LEFT JOIN `msz_news_categories` as c
    ON p.`category_id` = c.`category_id`
    LEFT JOIN `msz_users` as u
    ON p.`user_id` = u.`user_id`
    LEFT JOIN `msz_roles` as r
    ON u.`display_role` = r.`role_id`
    WHERE p.`is_featured` = true
    AND c.`is_hidden` = false
    ORDER BY p.`created_at` DESC
    LIMIT :offset, :take
');
$getPosts->bindValue('offset', $postsOffset);
$getPosts->bindValue('take', $postsTake);
$posts = $getPosts->execute() ? $getPosts->fetchAll() : [];

if (!$posts) {
    echo render_error(404);
    return;
}

echo tpl_render('news.index', compact('categories', 'posts'));
