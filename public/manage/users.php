<?php
use Misuzu\Database;

require_once __DIR__ . '/../../misuzu.php';

$db = Database::connection();
$templating = $app->getTemplating();

$isPostRequest = $_SERVER['REQUEST_METHOD'] === 'POST';
$queryQffset = (int)($_GET['o'] ?? 0);

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

        $templating->vars([
            'manage_users' => $manageUsers,
            'manage_users_count' => $manageUsersCount,
            'manage_users_range' => $usersTake,
            'manage_users_offset' => $queryQffset,
        ]);
        echo $templating->render('@manage.users.listing');
        break;

    case 'view':
        $userId = $_GET['u'] ?? null;

        if ($userId === null || ($userId = (int)$userId) < 1) {
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
            ORDER BY `user_id`
        ');
        $getUser->bindValue('user_id', $userId);
        $getUser->execute();
        $manageUser = $getUser->execute() ? $getUser->fetch() : [];

        if (!$manageUser) {
            echo 'Could not find that user.';
            break;
        }

        $getHasRoles = $db->prepare('
            SELECT `role_id`, `role_name`
            FROM `msz_roles`
            WHERE `role_id` IN (
                SELECT `role_id`
                FROM `msz_user_roles`
                WHERE `user_id` = :user_id
            )
        ');
        $getHasRoles->bindValue('user_id', $manageUser['user_id']);
        $hasRoles = $getHasRoles->execute() ? $getHasRoles->fetchAll() : [];

        $getAvailableRoles = $db->prepare('
            SELECT `role_id`, `role_name`
            FROM `msz_roles`
            WHERE `role_id` NOT IN (
                SELECT `role_id`
                FROM `msz_user_roles`
                WHERE `user_id` = :user_id
            )
        ');
        $getAvailableRoles->bindValue('user_id', $manageUser['user_id']);
        $availableRoles = $getAvailableRoles->execute() ? $getAvailableRoles->fetchAll() : [];

        if ($isPostRequest) {
            if (!tmp_csrf_verify($_POST['csrf'] ?? '')) {
                echo 'csrf err';
                break;
            }

            if (isset($_POST['avatar'])) {
                switch ($_POST['avatar']['mode'] ?? '') {
                    case 'delete':
                        user_avatar_delete($manageUser['user_id']);
                        break;

                    case 'upload':
                        user_avatar_set_from_path($manageUser['user_id'], $_FILES['avatar']['tmp_name']['file']);
                        break;
                }
            }

            if (isset($_POST['add_role'])) {
                user_role_add($manageUser['user_id'], $_POST['add_role']['role']);
            }

            if (isset($_POST['manage_roles'])) {
                switch ($_POST['manage_roles']['mode'] ?? '') {
                    case 'display':
                        user_role_set_display($manageUser['user_id'], $_POST['manage_roles']['role']);
                        break;

                    case 'remove':
                        if ((int)$_POST['manage_roles']['role'] !== MSZ_ROLE_MAIN) {
                            user_role_remove($manageUser['user_id'], $_POST['manage_roles']['role']);
                        }
                        break;
                }
            }

            header("Location: ?v=view&u={$manageUser['user_id']}");
            break;
        }

        $templating->vars([
            'available_roles' => $availableRoles,
            'has_roles' => $hasRoles,
            'view_user' => $manageUser,
        ]);
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

        $templating->vars([
            'manage_roles' => $manageRoles,
            'manage_roles_count' => $manageRolesCount,
            'manage_roles_range' => $rolesTake,
            'manage_roles_offset' => $queryQffset,
        ]);
        echo $templating->render('@manage.users.roles');
        break;

    case 'role':
        $roleId = $_GET['r'] ?? null;

        if ($isPostRequest) {
            if (!tmp_csrf_verify($_POST['csrf'] ?? '')) {
                echo 'csrf err';
                break;
            }

            if (!isset($_POST['role'])) {
                echo 'no';
                break;
            }

            $roleName = $_POST['role']['name'] ?? '';
            $roleNameLength = strlen($roleName);

            if ($roleNameLength < 1 || $roleNameLength > 255) {
                echo 'invalid name length';
                break;
            }

            $roleSecret = !empty($_POST['role']['secret']);

            $roleHierarchy = (int)($_POST['role']['hierarchy'] ?? -1);

            if ($roleHierarchy < 1 || $roleHierarchy > 100) {
                echo 'Invalid hierarchy value.';
                break;
            }

            $roleColour = colour_create();

            if (!empty($_POST['role']['colour']['inherit'])) {
                colour_set_inherit($roleColour);
            } else {
                foreach (['red', 'green', 'blue'] as $key) {
                    $value = (int)($_POST['role']['colour'][$key] ?? -1);
                    $func = 'colour_set_' . ucfirst($key);

                    if ($value < 0 || $value > 0xFF) {
                        echo 'invalid colour value';
                        break 2;
                    }

                    $func($roleColour, $value);
                }
            }

            $roleDescription = $_POST['role']['description'] ?? '';

            if (strlen($roleDescription) > 1000) {
                echo 'description is too long';
                break;
            }

            if ($roleId < 1) {
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
                $updateRole->bindValue('role_id', $roleId);
            }

            $updateRole->bindValue('role_name', $roleName);
            $updateRole->bindValue('role_hierarchy', $roleHierarchy);
            $updateRole->bindValue('role_secret', $roleSecret ? 1 : 0);
            $updateRole->bindValue('role_colour', $roleColour);
            $updateRole->bindValue('role_description', $roleDescription);
            $updateRole->execute();

            if ($roleId < 1) {
                $roleId = (int)$db->lastInsertId();
            }

            header("Location: ?v=role&r={$roleId}");
            break;
        }

        if ($roleId !== null) {
            if ($roleId < 1) {
                echo 'no';
                break;
            }

            $getEditRole = $db->prepare('
                SELECT *
                FROM `msz_roles`
                WHERE `role_id` = :role_id
            ');
            $getEditRole->bindValue('role_id', $roleId);
            $editRole = $getEditRole->execute() ? $getEditRole->fetch() : [];

            if (!$editRole) {
                echo 'invalid role';
                break;
            }

            $templating->vars(['edit_role' => $editRole]);
        }

        echo $templating->render('@manage.users.roles_create');
        break;
}
