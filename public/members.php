<?php
require_once '../misuzu.php';

use_legacy_style();

$usersOffset = max((int)($_GET['o'] ?? 0), 0);
$usersTake = 30;

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
        'column' => 'created_at',
        'default-dir' => 'desc',
        'title' => 'Registration Date',
    ],
    'last-online' => [
        'column' => 'last_seen',
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

$getRole = db_prepare('
    SELECT
        `role_id`, `role_name`, `role_colour`, `role_description`, `created_at`,
        (
            SELECT COUNT(`user_id`)
            FROM `msz_user_roles`
            WHERE `role_id` = r.`role_id`
        ) as `role_user_count`
    FROM `msz_roles` as r
    WHERE `role_id` = :role_id
');
$getRole->bindValue('role_id', $roleId);
$role = $getRole->execute() ? $getRole->fetch(PDO::FETCH_ASSOC) : [];

if (!$role) {
    echo render_error(404);
    return;
}

$roles = db_query('
    SELECT `role_id`, `role_name`, `role_colour`
    FROM `msz_roles`
    WHERE `role_secret` = 0
    ORDER BY `role_id`
')->fetchAll(PDO::FETCH_ASSOC);

$getUsers = db_prepare("
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
    ORDER BY u.`{$orderFields[$orderBy]['column']}` {$orderDir}
    LIMIT :offset, :take
");
$getUsers->bindValue('role_id', $role['role_id']);
$getUsers->bindValue('offset', $usersOffset);
$getUsers->bindValue('take', $usersTake);
$users = $getUsers->execute() ? $getUsers->fetchAll(PDO::FETCH_ASSOC) : [];

echo tpl_render('user.listing', [
    'roles' => $roles,
    'role' => $role,
    'users' => $users,
    'order_fields' => $orderFields,
    'order_directions' => $orderDirs,
    'order_field' => $orderBy,
    'order_direction' => $orderDir,
    'order_default' => $defaultOrder,
    'users_offset' => $usersOffset,
    'users_take' => $usersTake,
]);
