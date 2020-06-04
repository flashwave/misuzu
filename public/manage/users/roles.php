<?php
namespace Misuzu;

use Misuzu\Users\User;
use Misuzu\Users\UserRole;

require_once '../../../misuzu.php';

if(!User::hasCurrent() || !perms_check_user(MSZ_PERMS_USER, User::getCurrent()->getId(), MSZ_PERM_USER_MANAGE_ROLES)) {
    echo render_error(403);
    return;
}

$pagination = new Pagination(UserRole::countAll(true), 10);

if(!$pagination->hasValidOffset()) {
    echo render_error(404);
    return;
}

Template::render('manage.users.roles', [
    'manage_roles' => UserRole::all(true, $pagination),
    'manage_roles_pagination' => $pagination,
]);
