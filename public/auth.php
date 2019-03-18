<?php
// Delete this file in April 2019
use Misuzu\Request\RequestVar;

require_once '../misuzu.php';

switch (RequestVar::get()->select('m')->string()) {
    case 'logout':
        echo tpl_render('auth.logout');
        break;

    case 'reset':
        header('Location: ' . url('auth-reset'));
        break;

    case 'forgot':
        header('Location: ' . url('auth-forgot'));
        break;

    case 'login':
    default:
        header('Location: ' . url('auth-login'));
        break;

    case 'register':
        header('Location: ' . url('auth-register'));
        break;
}
