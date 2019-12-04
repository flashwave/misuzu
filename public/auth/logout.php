<?php
namespace Misuzu;

require_once '../../misuzu.php';

if(!user_session_active()) {
    url_redirect('index');
    return;
}

if(csrf_verify_request()) {
    setcookie('msz_auth', '', -9001, '/', '', !empty($_SERVER['HTTPS']), true);
    user_session_stop(true);
    url_redirect('index');
    return;
}

Template::render('auth.logout');
