<?php
use Aitemu\Route;
use Misuzu\Controllers\HomeController;

return [
    Route::get('/', 'index', HomeController::class),
];
