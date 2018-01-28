<?php
use Aitemu\Route;
use Misuzu\Controllers\AuthController;
use Misuzu\Controllers\HomeController;
use Misuzu\Controllers\UserController;

return [
    Route::get('/', 'index', HomeController::class),

    Route::get('/auth/login', 'login', AuthController::class),
    Route::post('/auth/login', 'login', AuthController::class),
    Route::get('/auth/register', 'register', AuthController::class),
    Route::post('/auth/register', 'register', AuthController::class),
    Route::get('/auth/logout', 'logout', AuthController::class),

    Route::get('/user/{id:i}', 'view', UserController::class),
];
