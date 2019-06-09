<?php
require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_USER, user_session_current('user_id'), MSZ_PERM_USER_MANAGE_WARNINGS)) {
    echo render_error(403);
    return;
}
