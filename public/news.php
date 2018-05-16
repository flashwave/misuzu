<?php
use Misuzu\Database;

require_once __DIR__ . '/../misuzu.php';

$db = Database::connection();
$templating = $app->getTemplating();

$category_id = isset($_GET['c']) ? (int)$_GET['c'] : null;
$post_id = isset($_GET['n']) ? (int)$_GET['n'] : null;
$page_id = (int)($_GET['p'] ?? 1);

if ($post_id !== null) {
    $getPost = $db->prepare('
        SELECT
            p.`post_id`, p.`post_title`, p.`post_text`, p.`created_at`,
            c.`category_id`, c.`category_name`,
            u.`user_id`, u.`username`,
            COALESCE(r.`role_colour`, CAST(0x40000000 AS UNSIGNED)) as `display_colour`
        FROM `msz_news_posts` as p
        LEFT JOIN `msz_news_categories` as c
        ON p.`category_id` = c.`category_id`
        LEFT JOIN `msz_users` as u
        ON p.`user_id` = u.`user_id`
        LEFT JOIN `msz_roles` as r
        ON u.`display_role` = r.`role_id`
        WHERE `post_id` = :post_id
    ');
    $getPost->bindValue(':post_id', $post_id, PDO::PARAM_INT);
    $post = $getPost->execute() ? $getPost->fetch() : false;

    if ($post === false) {
        http_response_code(404);
        echo $templating->render('errors.404');
        return;
    }

    echo $templating->render('news.post', compact('post'));
    return;
}

if ($category_id !== null) {
    $getCategory = $db->prepare('
        SELECT
            `category_id`, `category_name`, `category_description`
        FROM `msz_news_categories`
        WHERE `category_id` = :category_id
    ');
    $getCategory->bindValue(':category_id', $category_id, PDO::PARAM_INT);
    $category = $getCategory->execute() ? $getCategory->fetch() : false;

    if ($category === false) {
        http_response_code(404);
        echo $templating->render('errors.404');
        return;
    }

    $getPosts = $db->prepare('
        SELECT
            p.`post_id`, p.`post_title`, p.`post_text`, p.`created_at`,
            c.`category_id`, c.`category_name`,
            u.`user_id`, u.`username`,
            COALESCE(r.`role_colour`, CAST(0x40000000 AS UNSIGNED)) as `display_colour`
        FROM `msz_news_posts` as p
        LEFT JOIN `msz_news_categories` as c
        ON p.`category_id` = c.`category_id`
        LEFT JOIN `msz_users` as u
        ON p.`user_id` = u.`user_id`
        LEFT JOIN `msz_roles` as r
        ON u.`display_role` = r.`role_id`
        WHERE p.`category_id` = :category_id
        ORDER BY `created_at` DESC
        LIMIT 0, 5
    ');
    $getPosts->bindValue('category_id', $category['category_id'], PDO::PARAM_INT);
    $posts = $getPosts->execute() ? $getPosts->fetchAll() : false;

    //$posts = $category->posts()->orderBy('created_at', 'desc')->paginate(5, ['*'], 'p', $page_id);

    //if (!is_valid_page($posts, $page_id)) {
    if ($posts === false) {
        http_response_code(404);
        echo $templating->render('errors.404');
        return;
    }

    $getFeatured = $db->prepare('
        SELECT `post_id`, `post_title`
        FROM `msz_news_posts`
        WHERE `category_id` = :category_id
        AND `is_featured` = true
        ORDER BY `created_at` DESC
        LIMIT 10
    ');
    $getFeatured->bindValue('category_id', $category['category_id'], PDO::PARAM_INT);
    $featured = $getFeatured->execute() ? $getFeatured->fetchAll() : [];

    echo $templating->render('news.category', compact('category', 'posts', 'featured', 'page_id'));
    return;
}

$getCategories = $db->prepare('
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

$getPosts = $db->prepare('
    SELECT
        p.`post_id`, p.`post_title`, p.`post_text`, p.`created_at`,
        c.`category_id`, c.`category_name`,
        u.`user_id`, u.`username`,
        COALESCE(r.`role_colour`, CAST(0x40000000 AS UNSIGNED)) as `display_colour`
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
    LIMIT 0, 5
');
$posts = $getPosts->execute() ? $getPosts->fetchAll() : [];

//$posts = NewsPost::where('is_featured', true)->orderBy('created_at', 'desc')->paginate(5, ['*'], 'p', $page_id);

//if (!is_valid_page($posts, $page_id)) {
if ($posts === false) {
    http_response_code(404);
    echo $templating->render('errors.404');
    return;
}

echo $templating->render('news.index', compact('categories', 'posts', 'page_id'));
