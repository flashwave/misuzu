<?php
namespace Misuzu;

require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_USER, user_session_current('user_id'), MSZ_PERM_USER_MANAGE_USERS)) {
    echo render_error(403);
    return;
}

$manageUsersCount = (int)DB::query('
    SELECT COUNT(`user_id`)
    FROM `msz_users`
')->fetchColumn();

$usersPagination = pagination_create($manageUsersCount, 30);
$usersOffset = pagination_offset($usersPagination, pagination_param());

if(!pagination_is_valid_offset($usersOffset)) {
    echo render_error(404);
    return;
}

$getManageUsers = DB::prepare('
    SELECT
        u.`user_id`, u.`username`, u.`user_country`, r.`role_id`,
        u.`user_created`, u.`user_active`, u.`user_deleted`,
        INET6_NTOA(u.`register_ip`) AS `register_ip`, INET6_NTOA(u.`last_ip`) AS `last_ip`,
        COALESCE(u.`user_title`, r.`role_title`, r.`role_name`) AS `user_title`,
        COALESCE(u.`user_colour`, r.`role_colour`) AS `user_colour`
    FROM `msz_users` AS u
    LEFT JOIN `msz_roles` AS r
    ON u.`display_role` = r.`role_id`
    ORDER BY `user_id`
    LIMIT :offset, :take
');
$getManageUsers->bind('offset', $usersOffset);
$getManageUsers->bind('take', $usersPagination['range']);
$manageUsers = $getManageUsers->fetchAll();

echo tpl_render('manage.users.users', [
    'manage_users' => $manageUsers,
    'manage_users_pagination' => $usersPagination,
]);
