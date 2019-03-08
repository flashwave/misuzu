<?php
use Misuzu\Request\RequestVar;

require_once '../../misuzu.php';

if (!user_session_active()) {
    header(sprintf('Location: %s', url('index')));
    return;
}

if (csrf_verify('logout', RequestVar::get()->token->value('string', ''))) {
    setcookie('msz_auth', '', -9001, '/', '', true, true);
    user_session_stop(true);
    header(sprintf('Location: %s', url('index')));
    return;
}

echo tpl_render('auth.logout');
