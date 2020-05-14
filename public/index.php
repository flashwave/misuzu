<?php
namespace Misuzu;

use Misuzu\Http\HttpRequestMessage;
use Misuzu\Http\Routing\Router;
use Misuzu\Http\Routing\Route;

require_once '../misuzu.php';

$request = HttpRequestMessage::fromGlobals();

Router::setHandlerFormat('\Misuzu\Http\Handlers\%sHandler');
Router::setFilterFormat('\Misuzu\Http\Filters\%sFilter');
Router::addRoutes(
    // Home
    Route::get('/', 'index', 'Home'),

    // Assets
    Route::get('/assets/([a-zA-Z0-9\-]+)\.(css|js)', 'view', 'Assets'),

    // Info
    Route::get('/info', 'index', 'Info'),
    Route::get('/info/([A-Za-z0-9_/]+)', 'page', 'Info'),

    // Forum
    Route::group('/forum', 'Forum')->addChildren(
        Route::get('/mark-as-read', 'markAsReadGET')->addFilters('EnforceLogIn'),
        Route::post('/mark-as-read', 'markAsReadPOST')->addFilters('EnforceLogIn', 'ValidateCsrf'),
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

    // Redirects
    Route::get('/index.php', url('index')),
    Route::get('/info.php', url('info')),
    Route::get('/info.php/([A-Za-z0-9_/]+)', 'redir', 'Info'),
    Route::get('/auth.php', 'legacy', 'Auth'),
);

$response = Router::handle($request);
$response->setHeader('X-Powered-By', 'Misuzu');

$responseStatus = $response->getStatusCode();

header('HTTP/' . $response->getProtocolVersion() . ' ' . $responseStatus . ' ' . $response->getReasonPhrase());

foreach($response->getHeaders() as $headerName => $headerSet)
    foreach($headerSet as $headerLine)
        header("{$headerName}: {$headerLine}");

$responseBody = $response->getBody();

if($responseStatus >= 400 && $responseStatus <= 599 && ($responseBody === null || $responseBody->getSize() < 1)) {
    echo render_error($responseStatus);
} else {
    echo (string)$responseBody;
}

