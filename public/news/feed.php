<?php
namespace Misuzu;

use Misuzu\Feeds\Feed;
use Misuzu\Feeds\FeedItem;
use Misuzu\Feeds\AtomFeedSerializer;
use Misuzu\Feeds\RssFeedSerializer;
use Misuzu\Parsers\Parser;

require_once '../../misuzu.php';

$feedMode = trim($_SERVER['PATH_INFO'] ?? '', '/');

switch($feedMode) {
    case 'rss':
        $feedSerializer = new RssFeedSerializer;
        break;
    case 'atom':
        $feedSerializer = new AtomFeedSerializer;
        break;
}

if(!isset($feedSerializer)) {
    echo render_error(400);
    return;
}

$categoryId = !empty($_GET['c']) && is_string($_GET['c']) ? (int)$_GET['c'] : 0;

if(!empty($categoryId)) {
    $category = news_category_get($categoryId);

    if(empty($category)) {
        echo render_error(404);
        return;
    }
}

$posts = news_posts_get(0, 10, $category['category_id'] ?? null, empty($category));

if(!$posts) {
    echo render_error(404);
    return;
}

$feed = (new Feed)
    ->setTitle(Config::get('site.name', Config::TYPE_STR, 'Misuzu') . ' » ' . ($category['category_name'] ?? 'Featured News'))
    ->setDescription($category['category_description'] ?? 'A live featured news feed.')
    ->setContentUrl(url_prefix(false) . (empty($category) ? url('news-index') : url('news-category', ['category' => $category['category_id']])))
    ->setFeedUrl(url_prefix(false) . (empty($category) ? url("news-feed-{$feedMode}") : url("news-category-feed-{$feedMode}", ['category' => $category['category_id']])));

foreach($posts as $post) {
    $postUrl = url_prefix(false) . url('news-post', ['post' => $post['post_id']]);
    $commentsUrl = url_prefix(false) . url('news-post-comments', ['post' => $post['post_id']]);
    $authorUrl = url_prefix(false) . url('user-profile', ['user' => $post['user_id']]);

    $feedItem = (new FeedItem)
        ->setTitle($post['post_title'])
        ->setSummary(first_paragraph($post['post_text']))
        ->setContent(Parser::instance(Parser::MARKDOWN)->parseText($post['post_text']))
        ->setCreationDate(strtotime($post['post_created']))
        ->setUniqueId($postUrl)
        ->setContentUrl($postUrl)
        ->setCommentsUrl($commentsUrl)
        ->setAuthorName($post['username'])
        ->setAuthorUrl($authorUrl);

    if(!$feed->hasLastUpdate() || $feed->getLastUpdate() < $feedItem->getCreationDate())
        $feed->setLastUpdate($feedItem->getCreationDate());

    $feed->addItem($feedItem);
}

header("Content-Type: application/{$feedMode}+xml; charset=utf-8");

echo $feedSerializer->serializeFeed($feed);
