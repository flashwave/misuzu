<?php
namespace Misuzu\Controllers;

use Misuzu\Application;

class HomeController extends Controller
{
    public function index(): string
    {
        $twig = Application::getInstance()->templating;

        return $twig->render('home.landing');
    }

    public function isReady(): string
    {
        return 'no';
    }
}
