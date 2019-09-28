<?php
namespace Misuzu;

// Delete this file in April 2019

require_once '../misuzu.php';

$mode = !empty($_GET['m']) && is_string($_GET['m']) ? $_GET['m'] : '';

switch($mode) {
    case 'logout':
        echo tpl_render('auth.logout');
        break;

    case 'reset':
        url_redirect('auth-reset');
        break;

    case 'forgot':
        url_redirect('auth-forgot');
        break;

    case 'login':
    default:
        url_redirect('auth-login');
        break;

    case 'register':
        url_redirect('auth-register');
        break;
}
