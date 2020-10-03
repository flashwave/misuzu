<?php
namespace Misuzu;

use Misuzu\Http\HttpRequestMessage;
use Misuzu\Http\Routing\Router;
use Misuzu\Http\Routing\Route;

require_once __DIR__ . '/../misuzu.php';

$request = HttpRequestMessage::fromGlobals();

Router::setHandlerFormat('\Misuzu\Http\Handlers\%sHandler');
Router::setFilterFormat('\Misuzu\Http\Filters\%sFilter');

if(strpos($request->getUri()->getPath(), '.php') === false) {
    Router::addRoutes(
        // Home
        Route::get('/', 'index', 'Home'),

        // Assets
        Route::group('/assets', 'Assets')->addChildren(
            Route::get('/([a-zA-Z0-9\-]+)\.(css|js)', 'serveComponent'),
            Route::get('/avatar/([0-9]+)(?:\.png)?', 'serveAvatar'),
            Route::get('/profile-background/([0-9]+)(?:\.png)?', 'serveProfileBackground'),
        ),

        // Info
        Route::get('/info', 'index', 'Info'),
        Route::get('/info/([A-Za-z0-9_\-/]+)', 'page', 'Info'),

        // Changelog
        Route::get('/changelog', 'index', 'Changelog')->addChildren(
            Route::get('.atom', 'feedAtom'),
            Route::get('.rss', 'feedRss'),
            Route::get('/change/([0-9]+)', 'change'),
        ),

        // News
        Route::get('/news', 'index', 'News')->addChildren(
            Route::get('.atom', 'feedIndexAtom'),
            Route::get('.rss', 'feedIndexRss'),
            Route::get('/([0-9]+)', 'viewCategory'),
            Route::get('/([0-9]+).atom', 'feedCategoryAtom'),
            Route::get('/([0-9]+).rss', 'feedCategoryRss'),
            Route::get('/post/([0-9]+)', 'viewPost')
        ),

        // Forum
        Route::get('/forum', 'index', 'Forum.ForumIndex')->addChildren(
            Route::get('/mark-as-read', 'markAsRead')->addFilters('EnforceLogIn'),
            Route::post('/mark-as-read', 'markAsRead')->addFilters('EnforceLogIn', 'ValidateCsrf'),

            Route::get('/([0-9]+)', 'category', 'Forum.ForumCategory')->addChildren(
                Route::get('/create-topic', 'createView')->addFilters('EnforceLogIn'),
                Route::post('/create-topic', 'createAction')->addFilters('EnforceLogIn', 'ValidateCsrf'),
            ),

            Route::get('/topic/([0-9]+)', 'topic', 'Forum.ForumTopic')->addChildren(
                Route::get('/live', 'live')->addFilters('EnforceLogIn'),
                Route::post('/reply', 'reply')->addFilters('EnforceLogIn', 'ValidateCsrf'),
                Route::post('/delete', 'delete')->addFilters('EnforceLogIn', 'ValidateCsrf'),
                Route::post('/restore', 'restore')->addFilters('EnforceLogIn', 'ValidateCsrf'),
                Route::post('/nuke', 'nuke')->addFilters('EnforceLogIn', 'ValidateCsrf'),
                Route::post('/bump', 'bump')->addFilters('EnforceLogIn', 'ValidateCsrf'),
                Route::post('/lock', 'lock')->addFilters('EnforceLogIn', 'ValidateCsrf'),
                Route::post('/unlock', 'unlock')->addFilters('EnforceLogIn', 'ValidateCsrf'),
            ),

            Route::get('/post/([0-9]+)', 'post', 'Forum.ForumPost')->addChildren(
                Route::post('/', 'edit')->addFilters('EnforceLogIn', 'ValidateCsrf'),
                Route::post('/delete', 'delete')->addFilters('EnforceLogIn', 'ValidateCsrf'),
                Route::post('/restore', 'restore')->addFilters('EnforceLogIn', 'ValidateCsrf'),
                Route::post('/nuke', 'nuke')->addFilters('EnforceLogIn', 'ValidateCsrf'),
            ),

            Route::post('/poll/([0-9]+)', 'vote', 'Forum.ForumPoll')->addFilters('EnforceLogIn', 'ValidateCsrf'),
        ),

        // Sock Chat
        Route::create(['GET', 'POST'], '/_sockchat.php', 'phpFile', 'SockChat'),
        Route::group('/_sockchat', 'SockChat')->addChildren(
            Route::get('/emotes',  'emotes'),
            Route::get('/bans',    'bans'),
            Route::get('/login',   'login'),
            Route::post('/bump',   'bump'),
            Route::post('/verify', 'verify'),
        ),
    );
} else {
    Router::addRoutes(
        // Redirects
        Route::get('/index.php', url('index')),
        Route::get('/info.php', url('info')),
        Route::get('/settings.php', url('settings-index')),
        Route::get('/changelog.php', 'legacy', 'Changelog'),
        Route::get('/info.php/([A-Za-z0-9_\-/]+)', 'redir', 'Info'),
        Route::get('/auth.php', 'legacy', 'Auth'),
        Route::get('/news.php', 'legacy', 'News'),
        Route::get('/news.php/rss', 'legacy', 'News'),
        Route::get('/news.php/atom', 'legacy', 'News'),
        Route::get('/news/index.php', 'legacy', 'News'),
        Route::get('/news/category.php', 'legacy', 'News'),
        Route::get('/news/post.php', 'legacy', 'News'),
        Route::get('/news/feed.php', 'legacy', 'News'),
        Route::get('/news/feed.php/rss', 'legacy', 'News'),
        Route::get('/news/feed.php/atom', 'legacy', 'News'),
        Route::get('/user-assets.php', 'serveLegacy', 'Assets'),
        Route::create(['GET', 'POST'], '/forum/index.php', 'legacy', 'Forum.ForumIndex'),
        Route::get('/forum/forum.php', 'legacy', 'Forum.ForumCategory'),
        Route::get('/forum/topic.php', 'legacy', 'Forum.ForumTopic'),
        Route::get('/forum/post.php', 'legacy', 'Forum.ForumPost'),
    );
}

$response = Router::handle($request);
$response->setHeader('X-Powered-By', 'Misuzu');

$responseStatus = $response->getStatusCode();

header('HTTP/' . $response->getProtocolVersion() . ' ' . $responseStatus . ' ' . $response->getReasonPhrase());

foreach($response->getHeaders() as $name => $lines) {
    $firstLine = true;
    foreach($lines as $line) {
        header("{$name}: {$line}", $firstLine);
        $firstLine = false;
    }
}

$responseBody = $response->getBody();

if($responseStatus >= 400 && $responseStatus <= 599 && $responseBody === null)
    echo render_error($responseStatus);
else
    echo (string)$responseBody;
