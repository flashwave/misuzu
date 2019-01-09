<?php
require_once '../misuzu.php';

$roleId = (int)($_GET['r'] ?? MSZ_ROLE_MAIN);
$orderBy = mb_strtolower($_GET['ss'] ?? '');
$orderDir = mb_strtolower($_GET['sd'] ?? '');

$orderDirs = [
    'asc' => 'Ascending',
    'desc' => 'Descending',
];

$defaultOrder = 'last-online';
$orderFields = [
    'id' => [
        'column' => 'user_id',
        'default-dir' => 'asc',
        'title' => 'User ID',
    ],
    'name' => [
        'column' => 'username',
        'default-dir' => 'asc',
        'title' => 'Username',
    ],
    'country' => [
        'column' => 'user_country',
        'default-dir' => 'asc',
        'title' => 'Country',
    ],
    'registered' => [
        'column' => 'user_created',
        'default-dir' => 'desc',
        'title' => 'Registration Date',
    ],
    'last-online' => [
        'column' => 'user_active',
        'default-dir' => 'desc',
        'title' => 'Last Online',
    ],
];

if (empty($orderBy)) {
    $orderBy = $defaultOrder;
} elseif (!array_key_exists($orderBy, $orderFields)) {
    echo render_error(400);
    return;
}

if (empty($orderDir)) {
    $orderDir = $orderFields[$orderBy]['default-dir'];
} elseif (!array_key_exists($orderDir, $orderDirs)) {
    echo render_error(400);
    return;
}

$canManageUsers = perms_check(
    perms_get_user(MSZ_PERMS_USER, user_session_current('user_id', 0)),
    MSZ_PERM_USER_MANAGE_USERS
);

$getRole = db_prepare('
    SELECT
        `role_id`, `role_name`, `role_colour`, `role_description`, `role_created`,
        (
            SELECT COUNT(`user_id`)
            FROM `msz_user_roles`
            WHERE `role_id` = r.`role_id`
        ) as `role_user_count`
    FROM `msz_roles` as r
    WHERE `role_id` = :role_id
');
$getRole->bindValue('role_id', $roleId);
$role = db_fetch($getRole);

if (empty($role)) {
    echo render_error(404);
    return;
}

$usersPagination = pagination_create($role['role_user_count'], 30);
$usersOffset = pagination_offset($usersPagination, pagination_param());

if (!pagination_is_valid_offset($usersOffset)) {
    echo render_error(404);
    return;
}

$roles = db_query('
    SELECT `role_id`, `role_name`, `role_colour`
    FROM `msz_roles`
    WHERE `role_hidden` = 0
    ORDER BY `role_id`
')->fetchAll(PDO::FETCH_ASSOC);

$getUsers = db_prepare(sprintf(
    '
        SELECT
            u.`user_id`, u.`username`, u.`user_country`, r.`role_id`,
            COALESCE(u.`user_title`, r.`role_title`, r.`role_name`) as `user_title`,
            COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`
        FROM `msz_users` as u
        LEFT JOIN `msz_roles` as r
        ON r.`role_id` = u.`display_role`
        LEFT JOIN `msz_user_roles` as ur
        ON ur.`user_id` = u.`user_id`
        WHERE ur.`role_id` = :role_id
        %1$s
        ORDER BY u.`%2$s` %3$s
        LIMIT :offset, :take
    ',
    $canManageUsers ? '' : 'AND u.`user_deleted` IS NULL',
    $orderFields[$orderBy]['column'],
    $orderDir
));
$getUsers->bindValue('role_id', $role['role_id']);
$getUsers->bindValue('offset', $usersOffset);
$getUsers->bindValue('take', $usersPagination['range']);
$users = db_fetch_all($getUsers);

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
