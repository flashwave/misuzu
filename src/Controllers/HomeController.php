<?php
namespace Misuzu\Controllers;

use Misuzu\Application;

class HomeController extends Controller
{
    public function index(): string
    {
        $twig = Application::getInstance()->templating;

        $twig->addFunction('git_hash', [Application::class, 'gitCommitHash']);
        $twig->addFunction('git_branch', [Application::class, 'gitBranch']);

        return $twig->render('home.landing');
    }
}
