<?php
use Misuzu\Database;

require_once __DIR__ . '/../../misuzu.php';

$db = Database::connection();
$templating = $app->getTemplating();

$is_post_request = $_SERVER['REQUEST_METHOD'] === 'POST';
$queryQffset = (int)($_GET['o'] ?? 0);
$page_id = (int)($_GET['p'] ?? 1);

switch ($_GET['v'] ?? null) {
    case 'listing':
        $usersTake = 32;

        $manageUsersCount = $db->query('
            SELECT COUNT(`user_id`)
            FROM `msz_users`
        ')->fetchColumn();

        $getManageUsers = $db->prepare('
            SELECT
                u.`user_id`, u.`username`,
                COALESCE(r.`role_colour`, CAST(0x40000000 AS UNSIGNED)) as `colour`
            FROM `msz_users` as u
            LEFT JOIN `msz_roles` as r
            ON u.`display_role` = r.`role_id`
            LIMIT :offset, :take
        ');
        $getManageUsers->bindValue('offset', $queryQffset);
        $getManageUsers->bindValue('take', $usersTake);
        $manageUsers = $getManageUsers->execute() ? $getManageUsers->fetchAll() : [];

        //$manage_users = UserV1::paginate(32, ['*'], 'p', $page_id);
        $templating->vars([
            'manage_users' => $manageUsers,
            'manage_users_count' => $manageUsersCount,
            'manage_users_range' => $usersTake,
            'manage_users_offset' => $queryQffset,
        ]);
        echo $templating->render('@manage.users.listing');
        break;

    case 'view':
        $user_id = $_GET['u'] ?? null;

        if ($user_id === null || ($user_id = (int)$user_id) < 1) {
            echo 'no';
            break;
        }

        $getUser = $db->prepare('
            SELECT
                u.*,
                INET6_NTOA(u.`register_ip`) as `register_ip_decoded`,
                INET6_NTOA(u.`last_ip`) as `last_ip_decoded`,
                COALESCE(r.`role_colour`, CAST(0x40000000 AS UNSIGNED)) as `colour`
            FROM `msz_users` as u
            LEFT JOIN `msz_roles` as r
            ON u.`display_role` = r.`role_id`
            WHERE `user_id` = :user_id
        ');
        $getUser->bindValue('user_id', $user_id);
        $getUser->execute();
        $manageUser = $getUser->execute() ? $getUser->fetch() : [];

        if (!$manageUser) {
            echo 'Could not find that user.';
            break;
        }

        $templating->var('view_user', $manageUser);
        echo $templating->render('@manage.users.view');
        break;

    case 'roles':
        $rolesTake = 10;

        $manageRolesCount = $db->query('
            SELECT COUNT(`role_id`)
            FROM `msz_roles`
        ')->fetchColumn();

        $getManageRoles = $db->prepare('
            SELECT
                `role_id`, `role_colour`, `role_name`,
                (
                    SELECT COUNT(`user_id`)
                    FROM `msz_user_roles` as ur
                    WHERE ur.`role_id` = r.`role_id`
                ) as `users`
            FROM `msz_roles` as r
            LIMIT :offset, :take
        ');
        $getManageRoles->bindValue('offset', $queryQffset);
        $getManageRoles->bindValue('take', $rolesTake);
        $manageRoles = $getManageRoles->execute() ? $getManageRoles->fetchAll() : [];

        //$manage_roles = Role::paginate(10, ['*'], 'p', $page_id);
        $templating->vars([
            'manage_roles' => $manageRoles,
            'manage_roles_count' => $manageRolesCount,
            'manage_roles_range' => $rolesTake,
            'manage_roles_offset' => $queryQffset,
        ]);
        echo $templating->render('@manage.users.roles');
        break;

    case 'role':
        $role_id = $_GET['r'] ?? null;

        if ($is_post_request) {
            if (!tmp_csrf_verify($_POST['csrf'] ?? '')) {
                echo 'csrf err';
                break;
            }

            if (!isset($_POST['role'])) {
                echo 'no';
                break;
            }

            $role_name = $_POST['role']['name'] ?? '';
            $role_name_length = strlen($role_name);

            if ($role_name_length < 1 || $role_name_length > 255) {
                echo 'invalid name length';
                break;
            }

            $role_secret = !empty($_POST['role']['secret']);

            $role_hierarchy = (int)($_POST['role']['hierarchy'] ?? -1);

            if ($role_hierarchy < 1 || $role_hierarchy > 100) {
                echo 'Invalid hierarchy value.';
                break;
            }

            $role_colour = colour_create();

            if (!empty($_POST['role']['colour']['inherit'])) {
                colour_set_inherit($role_colour);
            } else {
                foreach (['red', 'green', 'blue'] as $key) {
                    $value = (int)($_POST['role']['colour'][$key] ?? -1);
                    $func = 'colour_set_' . ucfirst($key);

                    if ($value < 0 || $value > 0xFF) {
                        echo 'invalid colour value';
                        break 2;
                    }

                    $func($role_colour, $value);
                }
            }

            $role_description = $_POST['role']['description'] ?? '';

            if (strlen($role_description) > 1000) {
                echo 'description is too long';
                break;
            }

            if ($role_id < 1) {
                $updateRole = $db->prepare('
                    INSERT INTO `msz_roles`
                        (`role_name`, `role_hierarchy`, `role_secret`, `role_colour`, `role_description`, `created_at`)
                    VALUES
                        (:role_name, :role_hierarchy, :role_secret, :role_colour, :role_description, NOW())
                ');
            } else {
                $updateRole = $db->prepare('
                    UPDATE `msz_roles` SET
                    `role_name` = :role_name,
                    `role_hierarchy` = :role_hierarchy,
                    `role_secret` = :role_secret,
                    `role_colour` = :role_colour,
                    `role_description` = :role_description
                    WHERE `role_id` = :role_id
                ');
                $updateRole->bindValue('role_id', $role_id);
            }

            $updateRole->bindValue('role_name', $role_name);
            $updateRole->bindValue('role_hierarchy', $role_hierarchy);
            $updateRole->bindValue('role_secret', $role_secret ? 1 : 0);
            $updateRole->bindValue('role_colour', $role_colour);
            $updateRole->bindValue('role_description', $role_description);
            $updateRole->execute();

            if ($role_id < 1) {
                $role_id = (int)$db->lastInsertId();
            }

            header("Location: ?v=role&r={$role_id}");
            break;
        }

        if ($role_id !== null) {
            if ($role_id < 1) {
                echo 'no';
                break;
            }

            $getEditRole = $db->prepare('
                SELECT *
                FROM `msz_roles`
                WHERE `role_id` = :role_id
            ');
            $getEditRole->bindValue('role_id', $role_id);
            $edit_role = $getEditRole->execute() ? $getEditRole->fetch() : [];

            if (!$edit_role) {
                echo 'invalid role';
                break;
            }

            $templating->vars(compact('edit_role'));
        }

        echo $templating->render('@manage.users.roles_create');
        break;
}
