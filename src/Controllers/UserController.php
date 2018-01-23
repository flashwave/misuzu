<?php
namespace Misuzu\Controllers;

use Misuzu\Application;
use Misuzu\Users\User;

class UserController extends Controller
{
    public function view(int $userId): string
    {
        $app = Application::getInstance();
        $twig = $app->templating;
        $twig->vars(['profile' => User::findOrFail($userId)]);
        return $twig->render('user.view');
    }
}
