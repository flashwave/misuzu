<?php
namespace Misuzu;

require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_USER, user_session_current('user_id'), MSZ_PERM_USER_MANAGE_ROLES)) {
    echo render_error(403);
    return;
}

$manageRolesCount = (int)DB::query('
    SELECT COUNT(`role_id`)
    FROM `msz_roles`
')->fetchColumn();

$rolesPagination = new Pagination($manageRolesCount, 10);

if(!$rolesPagination->hasValidOffset()) {
    echo render_error(404);
    return;
}

$getManageRoles = DB::prepare('
    SELECT
        `role_id`, `role_colour`, `role_name`, `role_title`,
        (
            SELECT COUNT(`user_id`)
            FROM `msz_user_roles` as ur
            WHERE ur.`role_id` = r.`role_id`
        ) as `users`
    FROM `msz_roles` as r
    LIMIT :offset, :take
');
$getManageRoles->bind('offset', $rolesPagination->getOffset());
$getManageRoles->bind('take', $rolesPagination->getRange());
$manageRoles = $getManageRoles->fetchAll();

Template::render('manage.users.roles', [
    'manage_roles' => $manageRoles,
    'manage_roles_pagination' => $rolesPagination,
]);
