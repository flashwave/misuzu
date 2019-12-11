<?php
namespace Misuzu;

require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_NEWS, user_session_current('user_id'), MSZ_PERM_NEWS_MANAGE_POSTS)) {
    echo render_error(403);
    return;
}

$post = [];
$postId = (int)($_GET['p'] ?? null);
$categories = news_categories_get(0, 0, false, false, true);

if(!empty($_POST['post']) && CSRF::validateRequest()) {
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

    if(!$originalPostId && $isFeatured) {
        $twitterApiKey = Config::get('twitter.api.key', Config::TYPE_STR);
        $twitterApiSecret = Config::get('twitter.api.secret', Config::TYPE_STR);
        $twitterToken = Config::get('twitter.token.key', Config::TYPE_STR);
        $twitterTokenSecret = Config::get('twitter.token.secret', Config::TYPE_STR);

        if(!empty($twitterApiKey) && !empty($twitterApiSecret)
            && !empty($twitterToken) && !empty($twitterTokenSecret)) {
            Twitter::init($twitterApiKey, $twitterApiSecret, $twitterToken, $twitterTokenSecret);
            $url = url('news-post', ['post' => $postId]);
            Twitter::sendTweet("News :: {$title}\nhttps://{$_SERVER['HTTP_HOST']}{$url}");
        }
    }
}

if($postId > 0) {
    $post = news_post_get($postId);
}

Template::render('manage.news.post', compact('post', 'categories'));
