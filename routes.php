<?php
use Misuzu\Application;
use Misuzu\Controllers\AuthController;
use Misuzu\Controllers\HomeController;
use Misuzu\Controllers\UserController;

$routes = Application::getInstance()->router;

$routes->get(['/', 'main.index'], [HomeController::class, 'index']);

$routes->group(['prefix' => '/auth'], function ($routes) {
    $routes->get(['/login', 'auth.login'], [AuthController::class, 'login']);
    $routes->post(['/login', 'auth.login'], [AuthController::class, 'login']);

    $routes->get(['/logout', 'auth.logout'], [AuthController::class, 'logout']);

    $routes->get(['/register', 'auth.register'], [AuthController::class, 'register']);
    $routes->post(['/register', 'auth.register'], [AuthController::class, 'register']);
});

$routes->group(['prefix' => '/users'], function ($routes) {
    $routes->get(['/{id:i}', 'users.view'], [UserController::class, 'view']);
});
