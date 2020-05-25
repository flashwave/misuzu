<?php
namespace Misuzu;

use Misuzu\Users\User;
use Misuzu\Users\UserSession;

require_once '../../misuzu.php';

if(!UserSession::hasCurrent()) {
    url_redirect('index');
    return;
}

if(CSRF::validateRequest()) {
    setcookie('msz_auth', '', -9001, '/', '.' . $_SERVER['HTTP_HOST'], !empty($_SERVER['HTTPS']), true);
    setcookie('msz_auth', '', -9001, '/', '', !empty($_SERVER['HTTPS']), true);
    UserSession::getCurrent()->delete();
    UserSession::unsetCurrent();
    User::unsetCurrent();
    url_redirect('index');
    return;
}

Template::render('auth.logout');
