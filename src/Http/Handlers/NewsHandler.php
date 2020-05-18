<?php
namespace Misuzu\Http\Handlers;

use HttpResponse;
use HttpRequest;
use Misuzu\Config;
use Misuzu\DB;
use Misuzu\Pagination;
use Misuzu\Feeds\Feed;
use Misuzu\Feeds\FeedItem;
use Misuzu\Feeds\AtomFeedSerializer;
use Misuzu\Feeds\RssFeedSerializer;
use Misuzu\News\NewsCategory;
use Misuzu\News\NewsPost;
use Misuzu\News\NewsCategoryNotFoundException;
use Misuzu\News\NewsPostNotException;
use Misuzu\Parsers\Parser;
use Misuzu\Users\User;
use Misuzu\Users\UserNotFoundException;

final class NewsHandler extends Handler {
    public function index(HttpResponse $response, HttpRequest $request) {
        $categories = NewsCategory::all();
        $newsPagination = new Pagination(NewsPost::countAll(true), 5);

        if(!$newsPagination->hasValidOffset())
            return 404;

        $response->setTemplate('news.index', [
            'categories' => $categories,
            'posts' => NewsPost::all($newsPagination, true),
            'news_pagination' => $newsPagination,
        ]);
    }

    public function viewCategory(HttpResponse $response, HttpRequest $request, int $categoryId) {
        try {
            $categoryInfo = NewsCategory::byId($categoryId);
        } catch(NewsCategoryNotFoundException $ex) {
            return 404;
        }

        $categoryPagination = new Pagination(NewsPost::countByCategory($categoryInfo), 5);
        if(!$categoryPagination->hasValidOffset())
            return 404;

        $response->setTemplate('news.category', [
            'category_info' => $categoryInfo,
            'posts' => $categoryInfo->posts($categoryPagination),
            'news_pagination' => $categoryPagination,
        ]);
    }

    public function viewPost(HttpResponse $response, HttpRequest $request, int $postId) {
        try {
            $postInfo = NewsPost::byId($postId);
        } catch(NewsPostNotFoundException $ex) {
            return 404;
        }

        if(!$postInfo->isPublished() || $postInfo->isDeleted())
            return 404;

        $postInfo->ensureCommentsSection();
        $commentsInfo = $postInfo->getCommentSection();
        try {
            $commentsUser = User::byId(user_session_current('user_id', 0));
        } catch(UserNotFoundException $ex) {
            $commentsUser = null;
        }

        $response->setTemplate('news.post', [
            'post_info' => $postInfo,
            'comments_info'  => $commentsInfo,
            'comments_user'  => $commentsUser,
        ]);

    }

    private function createFeed(string $feedMode, ?NewsCategory $categoryInfo, array $posts): Feed {
        $hasCategory = !empty($categoryInfo);
        $pagination = new Pagination(10);
        $posts = $hasCategory ? $categoryInfo->posts($pagination) : NewsPost::all($pagination, true);

        $feed = (new Feed)
            ->setTitle(Config::get('site.name', Config::TYPE_STR, 'Misuzu') . ' » ' . ($hasCategory ? $categoryInfo->getName() : 'Featured News'))
            ->setDescription($hasCategory ? $categoryInfo->getDescription() : 'A live featured news feed.')
            ->setContentUrl(url_prefix(false) . ($hasCategory ? url('news-category', ['category' => $categoryInfo->getId()]) : url('news-index')))
            ->setFeedUrl(url_prefix(false) . ($hasCategory ? url("news-category-feed-{$feedMode}", ['category' => $categoryInfo->getId()]) : url("news-feed-{$feedMode}")));

        foreach($posts as $post) {
            $postUrl = url_prefix(false) . url('news-post', ['post' => $post->getId()]);
            $commentsUrl = url_prefix(false) . url('news-post-comments', ['post' => $post->getId()]);
            $authorUrl = url_prefix(false) . url('user-profile', ['user' => $post->getUser()->getId()]);

            $feedItem = (new FeedItem)
                ->setTitle($post->getTitle())
                ->setSummary(first_paragraph($post->getText()))
                ->setContent(Parser::instance(Parser::MARKDOWN)->parseText($post->getText()))
                ->setCreationDate(strtotime($post->getCreatedTime()))
                ->setUniqueId($postUrl)
                ->setContentUrl($postUrl)
                ->setCommentsUrl($commentsUrl)
                ->setAuthorName($post->getUser()->getUsername())
                ->setAuthorUrl($authorUrl);

            if(!$feed->hasLastUpdate() || $feed->getLastUpdate() < $feedItem->getCreationDate())
                $feed->setLastUpdate($feedItem->getCreationDate());

            $feed->addItem($feedItem);
        }

        return $feed;
    }

    public function feedIndexAtom(HttpResponse $response, HttpRequest $request) {
        $response->setContentType('application/atom+xml; charset=utf-8');
        return (new AtomFeedSerializer)->serializeFeed(
            self::createFeed('atom', null, NewsPost::all(new Pagination(10), true))
        );
    }

    public function feedIndexRss(HttpResponse $response, HttpRequest $request) {
        $response->setContentType('application/rss+xml; charset=utf-8');
        return (new RssFeedSerializer)->serializeFeed(
            self::createFeed('rss', null, NewsPost::all(new Pagination(10), true))
        );
    }

    public function feedCategoryAtom(HttpResponse $response, HttpRequest $request, int $categoryId) {
        try {
            $categoryInfo = NewsCategory::byId($categoryId);
        } catch(NewsCategoryNotFoundException $ex) {
            return 404;
        }

        $response->setContentType('application/atom+xml; charset=utf-8');
        return (new AtomFeedSerializer)->serializeFeed(
            self::createFeed('atom', $categoryInfo, $categoryInfo->posts(new Pagination(10)))
        );
    }

    public function feedCategoryRss(HttpResponse $response, HttpRequest $request, int $categoryId) {
        try {
            $categoryInfo = NewsCategory::byId($categoryId);
        } catch(NewsCategoryNotFoundException $ex) {
            return 404;
        }

        $response->setContentType('application/rss+xml; charset=utf-8');
        return (new RssFeedSerializer)->serializeFeed(
            self::createFeed('rss', $categoryInfo, $categoryInfo->posts(new Pagination(10)))
        );
    }

    public function legacy(HttpResponse $response, HttpRequest $request) {
        $location = url('news-index');

        switch('/' . trim($request->getUri()->getPath(), '/')) {
            case '/news/index.php':
                $location = url('news-index', [
                    'page' => $request->getQueryParam('page', FILTER_SANITIZE_NUMBER_INT),
                ]);
                break;

            case '/news/category.php':
                $location = url('news-category', [
                    'category' => $request->getQueryParam('c', FILTER_SANITIZE_NUMBER_INT),
                    'page' => $request->getQueryParam('p', FILTER_SANITIZE_NUMBER_INT),
                ]);
                break;

            case '/news/post.php':
                $location = url('news-post', [
                    'post' => $request->getQueryParam('p', FILTER_SANITIZE_NUMBER_INT),
                ]);
                break;

            case '/news/feed.php':
                return 400;

            case '/news/feed.php/rss':
            case '/news/feed.php/atom':
                $feedType = basename($request->getUri()->getPath());
                $catId = $request->getQueryParam('c', FILTER_SANITIZE_NUMBER_INT);
                $location = url($catId > 0 ? "news-category-feed-{$feedType}" : "news-feed-{$feedType}", ['category' => $catId]);
                break;

            case '/news.php/rss':
            case '/news.php/atom':
                $feedType = basename($request->getUri()->getPath());
            case '/news.php':
                $postId = $request->getQueryParam('n', FILTER_SANITIZE_NUMBER_INT) ?? $request->getQueryParam('p', FILTER_SANITIZE_NUMBER_INT);
                if($postId > 0)
                    $location = url('news-post', ['post' => $postId]);
                else {
                    $catId = $request->getQueryParam('c', FILTER_SANITIZE_NUMBER_INT);
                    $pageId = $request->getQueryParam('page', FILTER_SANITIZE_NUMBER_INT);
                    $location = url($catId > 0 ? (isset($feedType) ? "news-category-feed-{$feedType}" : 'news-category') : (isset($feedType) ? "news-feed-{$feedType}" : 'news-index'), ['category' => $catId, 'page' => $pageId]);
                }
                break;
        }

        $response->redirect($location, true);
    }
}
