<?php
namespace Misuzu;

use Misuzu\Http\HttpServerRequestMessage;
use Misuzu\Http\Filters\Filter;
use Misuzu\Http\Handlers\Handler;
use Misuzu\Http\Routing\Router;
use Misuzu\Http\Routing\Route;

require_once '../misuzu.php';

$request = HttpServerRequestMessage::fromGlobals();

$router = new Router;
$router->setInstance();
$router->addRoutes(
    // Home
    Route::get('/', Handler::call('index@Home')),

    // Info
    Route::get('/info', Handler::call('index@Info')),
    Route::get('/info/([A-Za-z0-9_/]+)', true, Handler::call('page@Info')),

    // Forum
    Route::get('/forum/mark-as-read', Handler::call('markAsReadGET@Forum'))->addFilters(Filter::call('EnforceLogIn')),
    Route::post('/forum/mark-as-read', Handler::call('markAsReadPOST@Forum'))->addFilters(Filter::call('EnforceLogIn'), Filter::call('ValidateCsrf')),

    // Sock Chat
    Route::create(['GET', 'POST'], '/_sockchat.php', Handler::call('phpFile@SockChat')),
    Route::get('/_sockchat/emotes', Handler::call('emotes@SockChat')),
    Route::get('/_sockchat/bans', Handler::call('bans@SockChat')),
    Route::get('/_sockchat/login', Handler::call('login@SockChat')),
    Route::post('/_sockchat/bump', Handler::call('bump@SockChat')),
    Route::post('/_sockchat/verify', Handler::call('verify@SockChat')),

    // Redirects
    Route::get('/index.php', Handler::redirect(url('index'), true)),
    Route::get('/info.php', Handler::redirect(url('info'), true)),
    Route::get('/info.php/([A-Za-z0-9_/]+)', true, Handler::call('redir@Info')),
    Route::get('/auth.php', Handler::call('legacy@Auth'))
);

$response = $router->handle($request);
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

