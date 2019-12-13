<?php
namespace Misuzu;

use Misuzu\Http\HttpServerRequestMessage;
use Misuzu\Http\Handlers\Handler;
use Misuzu\Http\Routing\Router;
use Misuzu\Http\Routing\Route;

require_once '../misuzu.php';

$request = HttpServerRequestMessage::fromGlobals();

$router = new Router;
$router->setInstance();
$router->addRoutes(
    // Home
    Route::get('/', Handler::call('index@HomeHandler')),

    // Info
    Route::get('/info', Handler::call('index@InfoHandler')),
    Route::get('/info/([A-Za-z0-9_/]+)', true, Handler::call('page@InfoHandler')),

    // Redirects
    Route::get('/index.php', Handler::redirect(url('index'), true)),
    Route::get('/info.php', Handler::redirect(url('info'), true)),
    Route::get('/info.php/([A-Za-z0-9_/]+)', true, Handler::call('redir@InfoHandler')),
    Route::get('/auth.php', Handler::call('legacy@AuthHandler'))
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

