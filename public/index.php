<?php
use Misuzu\Database;
use Misuzu\News\NewsPost;

require_once __DIR__ . '/../misuzu.php';

//$featured_news = NewsPost::where('is_featured', true)->orderBy('created_at', 'desc')->take(3)->get();
$featuredNews = [];

$fetchNews = Database::connection()
    ->query('
SELECT
    p.`post_id`, p.`post_title`, p.`post_text`, p.`created_at`,
    u.`user_id`, u.`username`,
    COALESCE(r.`role_colour`, CAST(0x40000000 AS UNSIGNED)) as `display_colour`
FROM `msz_news_posts` as p
LEFT JOIN `msz_users` as u
ON p.`user_id` = u.`user_id`
LEFT JOIN `msz_roles` as r
ON u.`display_role` = r.`role_id`
WHERE p.`is_featured` = true
ORDER BY p.`created_at` DESC
LIMIT 3
    ');

while (($newsPost = $fetchNews->fetchObject(NewsPost::class)) !== false) {
    $featuredNews['post'] = $newsPost;
}

var_dump($featuredNews);


echo $app->getTemplating()->render('home.landing', compact('featuredNews'));
