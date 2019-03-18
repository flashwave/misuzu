<?php
// Delete this file in April 2019

require_once '../misuzu.php';

$mode = !empty($_GET['m']) && is_string($_GET['m']) ? $_GET['m'] : '';

switch ($mode) {
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
