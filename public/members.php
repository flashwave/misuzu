<?php
namespace Misuzu;

require_once '../misuzu.php';

$roleId = !empty($_GET['r']) && is_string($_GET['r']) ? (int)$_GET['r'] : MSZ_ROLE_MAIN;
$orderBy = !empty($_GET['ss']) && is_string($_GET['ss']) ? mb_strtolower($_GET['ss']) : '';
$orderDir = !empty($_GET['sd']) && is_string($_GET['sd']) ? mb_strtolower($_GET['sd']) : '';

$orderDirs = [
    'asc' => 'Ascending',
    'desc' => 'Descending',
];

$defaultOrder = 'last-online';
$orderFields = [
    'id' => [
        'column' => 'u.`user_id`',
        'default-dir' => 'asc',
        'title' => 'User ID',
    ],
    'name' => [
        'column' => 'u.`username`',
        'default-dir' => 'asc',
        'title' => 'Username',
    ],
    'country' => [
        'column' => 'u.`user_country`',
        'default-dir' => 'asc',
        'title' => 'Country',
    ],
    'registered' => [
        'column' => 'u.`user_created`',
        'default-dir' => 'desc',
        'title' => 'Registration Date',
    ],
    'last-online' => [
        'column' => 'u.`user_active`',
        'default-dir' => 'desc',
        'title' => 'Last Online',
    ],
    'forum-topics' => [
        'column' => '`user_count_topics`',
        'default-dir' => 'desc',
        'title' => 'Forum Topics',
    ],
    'forum-posts' => [
        'column' => '`user_count_posts`',
        'default-dir' => 'desc',
        'title' => 'Forum Posts',
    ],
    'following' => [
        'column' => '`user_count_following`',
        'default-dir' => 'desc',
        'title' => 'Following',
    ],
    'followers' => [
        'column' => '`user_count_followers`',
        'default-dir' => 'desc',
        'title' => 'Followers',
    ],
];

if(empty($orderBy)) {
    $orderBy = $defaultOrder;
} elseif(!array_key_exists($orderBy, $orderFields)) {
    echo render_error(400);
    return;
}

if(empty($orderDir)) {
    $orderDir = $orderFields[$orderBy]['default-dir'];
} elseif(!array_key_exists($orderDir, $orderDirs)) {
    echo render_error(400);
    return;
}

$canManageUsers = perms_check_user(
    MSZ_PERMS_USER, user_session_current('user_id', 0),
    MSZ_PERM_USER_MANAGE_USERS
);

$role = user_role_get($roleId);

if(empty($role)) {
    echo render_error(404);
    return;
}

$usersPagination = pagination_create($role['role_user_count'], 15);
$usersOffset = pagination_offset($usersPagination, pagination_param());

if($usersOffset > 0 && !pagination_is_valid_offset($usersOffset)) {
    echo render_error(404);
    return;
}

$roles = user_role_all();

$getUsers = DB::prepare(sprintf(
    '
        SELECT
            :current_user_id AS `current_user_id`,
            u.`user_id`, u.`username`, u.`user_country`,
            u.`user_created`, u.`user_active`, r.`role_id`,
            COALESCE(u.`user_title`, r.`role_title`) AS `user_title`,
            COALESCE(u.`user_colour`, r.`role_colour`) AS `user_colour`,
            (
                SELECT COUNT(`topic_id`)
                FROM `msz_forum_topics`
                WHERE `user_id` = u.`user_id`
                AND `topic_deleted` IS NULL
            ) AS `user_count_topics`,
            (
                SELECT COUNT(`post_Id`)
                FROM `msz_forum_posts`
                WHERE `user_id` = u.`user_id`
                AND `post_deleted` IS NULL
            ) AS `user_count_posts`,
            (
                SELECT COUNT(`subject_id`)
                FROM `msz_user_relations`
                WHERE `user_id` = u.`user_id`
                AND `relation_type` = %4$d
            ) AS `user_count_following`,
            (
                SELECT COUNT(`user_id`)
                FROM `msz_user_relations`
                WHERE `subject_id` = u.`user_id`
                AND `relation_type` = %4$d
            ) AS `user_count_followers`,
            (
                SELECT `relation_type` = %4$d
                FROM `msz_user_relations`
                WHERE `user_id` = `current_user_id`
                AND `subject_id` = u.`user_id`
            ) AS `user_is_following`,
            (
                SELECT `relation_type` = %4$d
                FROM `msz_user_relations`
                WHERE `user_id` = u.`user_id`
                AND `subject_id` = `current_user_id`
            ) AS `user_is_follower`
        FROM `msz_users` AS u
        LEFT JOIN `msz_roles` AS r
        ON r.`role_id` = u.`display_role`
        LEFT JOIN `msz_user_roles` AS ur
        ON ur.`user_id` = u.`user_id`
        WHERE ur.`role_id` = :role_id
        %1$s
        ORDER BY %2$s %3$s
        LIMIT %5$d, %6$d
    ',
    $canManageUsers ? '' : 'AND u.`user_deleted` IS NULL',
    $orderFields[$orderBy]['column'],
    $orderDir,
    MSZ_USER_RELATION_FOLLOW,
    $usersOffset < 0 ? 0 : $usersOffset,
    isset($usersPagination['range']) ? $usersPagination['range'] : 0
));
$getUsers->bind('role_id', $role['role_id']);
$getUsers->bind('current_user_id', user_session_current('user_id', 0));
$users = $getUsers->fetchAll();

if(empty($users)) {
    http_response_code(404);
}

echo tpl_render('user.listing', [
    'roles' => $roles,
    'role' => $role,
    'users' => $users,
    'order_fields' => $orderFields,
    'order_directions' => $orderDirs,
    'order_field' => $orderBy,
    'order_direction' => $orderDir,
    'order_default' => $defaultOrder,
    'can_manage_users' => $canManageUsers,
    'users_pagination' => $usersPagination,
]);
