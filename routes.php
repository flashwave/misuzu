<?php
use Aitemu\Route;
use Misuzu\Controllers\AuthController;
use Misuzu\Controllers\HomeController;

return [
    Route::get('/', 'index', HomeController::class),
    Route::get('/is_ready', 'isReady', HomeController::class),

    Route::get('/auth/login', 'login', AuthController::class),
    Route::post('/auth/login', 'login', AuthController::class),
    Route::get('/auth/register', 'register', AuthController::class),
    Route::post('/auth/register', 'register', AuthController::class),
    Route::get('/auth/logout', 'logout', AuthController::class),
];
