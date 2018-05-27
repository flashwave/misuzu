<?php
use Misuzu\Database;

require_once __DIR__ . '/../misuzu.php';

$usersOffset = max((int)($_GET['o'] ?? 0), 0);
$usersTake = max(min((int)($_GET['t'] ?? 15), 30), 6);

$roleId = (int)($_GET['r'] ?? MSZ_ROLE_MAIN);
$orderBy = strtolower($_GET['ss'] ?? '');
$orderDir = strtoupper($_GET['sd'] ?? '');

$orderDirs = [
    'ASC' => 'Ascending',
    'DESC' => 'Descending',
];

$orderFields = [
    'id' => [
        'column' => 'user_id',
        'default-dir' => 'ASC',
        'title' => 'User ID',
    ],
    'name' => [
        'column' => 'username',
        'default-dir' => 'ASC',
        'title' => 'Username',
    ],
    'country' => [
        'column' => 'user_country',
        'default-dir' => 'ASC',
        'title' => 'Country',
    ],
    'registered' => [
        'column' => 'created_at',
        'default-dir' => 'DESC',
        'title' => 'Registration Date',
    ],
    'last-online' => [
        'column' => 'last_seen',
        'default-dir' => 'DESC',
        'title' => 'Last Online',
    ],
];

if (empty($orderBy)) {
    $orderBy = 'last-online';
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

$db = Database::connection();
$tpl = $app->getTemplating();

$getRole = $db->prepare('
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
$role = $getRole->execute() ? $getRole->fetch() : [];

if (!$role) {
    echo render_error(404);
    return;
}

$getRoles = $db->prepare('
    SELECT `role_id`, `role_name`, `role_colour`
    FROM `msz_roles`
    WHERE `role_secret` = 0
    ORDER BY `role_id`
');
$roles = $getRoles->execute() ? $getRoles->fetchAll() : [];

$getUsers = $db->prepare("
    SELECT
        u.`user_id`, u.`username`, u.`user_country`,
        u.`created_at` as `user_joined`, u.`last_seen` as `user_last_seen`,
        COALESCE(u.`user_title`, r.`role_title`) as `user_title`,
        COALESCE(r.`role_colour`, CAST(0x40000000 AS UNSIGNED)) as `display_colour`,
        (
            SELECT COUNT(`topic_id`)
            FROM `msz_forum_topics`
            WHERE `user_id` = u.`user_id`
            AND `topic_deleted` IS NULL
        ) as `user_topic_count`,
        (
            SELECT COUNT(`post_id`)
            FROM `msz_forum_posts`
            WHERE `user_id` = u.`user_id`
            AND `post_deleted` IS NULL
        ) as `user_post_count`
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
$users = $getUsers->execute() ? $getUsers->fetchAll() : [];

echo $tpl->render('user.listing', [
    'roles' => $roles,
    'role' => $role,
    'users' => $users,
    'order_fields' => $orderFields,
    'order_directions' => $orderDirs,
    'order_field' => $orderBy,
    'order_direction' => $orderDir,
    'users_offset' => $usersOffset,
    'users_take' => $usersTake,
]);
