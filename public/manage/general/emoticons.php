<?php
require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_GENERAL, user_session_current('user_id'), MSZ_PERM_GENERAL_MANAGE_EMOTICONS)) {
    echo render_error(403);
    return;
}

echo tpl_render('manage.general.emoticons', [
    'emotes' => emotes_list(PHP_INT_MAX),
]);
