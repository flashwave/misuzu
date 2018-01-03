<?php
namespace Misuzu\Controllers;

use Misuzu\Application;
use Misuzu\Database;
use Misuzu\AyaseUser;

class HomeController extends Controller
{
    public function index(): string
    {
        $app = Application::getInstance();
        $twig = $app->templating;

        return $twig->render('home.landing');
    }

    public function isReady(): string
    {
        return 'no';
    }
}
