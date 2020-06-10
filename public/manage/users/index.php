<?php
namespace Misuzu;

use Misuzu\Users\User;

require_once '../../../misuzu.php';

if(!User::hasCurrent() || !perms_check_user(MSZ_PERMS_USER, User::getCurrent()->getId(), MSZ_PERM_USER_MANAGE_USERS)) {
    echo render_error(403);
    return;
}

$pagination = new Pagination(User::countAll(true), 30);

if(!$pagination->hasValidOffset()) {
    echo render_error(404);
    return;
}

Template::render('manage.users.users', [
    'manage_users' => User::all(true, $pagination),
    'manage_users_pagination' => $pagination,
]);
