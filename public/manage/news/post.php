<?php
namespace Misuzu;

use Misuzu\News\NewsCategory;
use Misuzu\News\NewsPost;
use Misuzu\News\NewsPostNotFoundException;

require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_NEWS, user_session_current('user_id'), MSZ_PERM_NEWS_MANAGE_POSTS)) {
    echo render_error(403);
    return;
}

$postId = (int)filter_input(INPUT_GET, 'p', FILTER_SANITIZE_NUMBER_INT);
if($postId > 0)
    try {
        $postInfo = NewsPost::byId($postId);
        Template::set('post_info', $postInfo);
    } catch(NewsPostNotFoundException $ex) {
        echo render_error(404);
        return;
    }

$categories = NewsCategory::all(null, true);

if(!empty($_POST['post']) && CSRF::validateRequest()) {
    if(!isset($postInfo)) {
        $postInfo = new NewsPost;
        $isNew = true;
    }

    $currentUserId = user_session_current('user_id');
    $postInfo->setTitle( $_POST['post']['title'])
        ->setText($_POST['post']['text'])
        ->setCategoryId($_POST['post']['category'])
        ->setFeatured(!empty($_POST['post']['featured']));

    if(!empty($isNew))
        $postInfo->setUserId($currentUserId);

    $postInfo->save();

    audit_log(
        empty($isNew)
            ? MSZ_AUDIT_NEWS_POST_EDIT
            : MSZ_AUDIT_NEWS_POST_CREATE,
        $currentUserId,
        [$postInfo->getId()]
    );

    if(!empty($isNew)) {
        if($postInfo->isFeatured()) {
            $twitterApiKey = Config::get('twitter.api.key', Config::TYPE_STR);
            $twitterApiSecret = Config::get('twitter.api.secret', Config::TYPE_STR);
            $twitterToken = Config::get('twitter.token.key', Config::TYPE_STR);
            $twitterTokenSecret = Config::get('twitter.token.secret', Config::TYPE_STR);

            if(!empty($twitterApiKey) && !empty($twitterApiSecret)
                && !empty($twitterToken) && !empty($twitterTokenSecret)) {
                Twitter::init($twitterApiKey, $twitterApiSecret, $twitterToken, $twitterTokenSecret);
                $url = url('news-post', ['post' => $postInfo->getId()]);
                Twitter::sendTweet("News :: {$postInfo->getTitle()}\nhttps://{$_SERVER['HTTP_HOST']}{$url}");
            }
        }

        header('Location: ' . url('manage-news-post', ['post' => $postInfo->getId()]));
        return;
    }
}

Template::render('manage.news.post', [
    'categories' => $categories,
]);
