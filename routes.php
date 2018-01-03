<?php
use Aitemu\Route;
use Misuzu\Controllers\HomeController;

return [
    Route::get('/', 'index', HomeController::class),
    Route::get('/is_ready', 'isReady', HomeController::class),
];
