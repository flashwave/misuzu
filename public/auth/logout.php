<?php
require_once '../../misuzu.php';

if (!user_session_active()) {
    header(sprintf('Location: %s', url('index')));
    return;
}

if (!empty($_GET['token']) && is_string($_GET['token']) && csrf_verify('logout', $_GET['token'])) {
    setcookie('msz_auth', '', -9001, '/', '', true, true);
    user_session_stop(true);
    header(sprintf('Location: %s', url('index')));
    return;
}

echo tpl_render('auth.logout');
