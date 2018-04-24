<?php
use Misuzu\Colour;
use Misuzu\Users\Role;
use Misuzu\Users\User;

require_once __DIR__ . '/../../misuzu.php';

$is_post_request = $_SERVER['REQUEST_METHOD'] === 'POST';
$page_id = (int)($_GET['p'] ?? 1);

switch ($_GET['v'] ?? null) {
    case 'listing':
        $manage_users = User::paginate(32, ['*'], 'p', $page_id);
        $app->getTemplating()->vars(compact('manage_users'));
        echo $app->getTemplating()->render('@manage.users.listing');
        break;

    case 'view':
        $user_id = $_GET['u'] ?? null;

        if ($user_id === null || ($user_id = (int)$user_id) < 1) {
            echo 'no';
            break;
        }

        $view_user = User::find($user_id);

        if ($view_user === null) {
            echo 'Could not find that user.';
            break;
        }

        $app->getTemplating()->var('view_user', $view_user);
        echo $app->getTemplating()->render('@manage.users.view');
        break;

    case 'roles':
        $manage_roles = Role::paginate(32, ['*'], 'p', $page_id);
        $app->getTemplating()->vars(compact('manage_roles'));
        echo $app->getTemplating()->render('@manage.users.roles');
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

            $role_colour = Colour::none();
            $role_colour->setInherit(!empty($_POST['role']['colour']['inherit']));

            if (!$role_colour->getInherit()) {
                foreach (['red', 'green', 'blue'] as $key) {
                    $value = (int)($_POST['role']['colour'][$key] ?? -1);
                    $setter = 'set' . ucfirst($key);

                    if ($value < 0 || $value > 0xFF) {
                        echo 'invalid colour value';
                        break 2;
                    }

                    $role_colour->{$setter}($value);
                }
            }

            $role_description = $_POST['role']['description'] ?? '';

            if (strlen($role_description) > 1000) {
                echo 'description is too long';
                break;
            }

            $edit_role = $role_id < 1 ? new Role : Role::find($role_id);
            $edit_role->role_name = $role_name;
            $edit_role->role_hierarchy = $role_hierarchy;
            $edit_role->role_secret = $role_secret;
            $edit_role->role_colour = $role_colour;
            $edit_role->role_description = $role_description;
            $edit_role->save();

            header("Location: ?v=role&r={$edit_role->role_id}");
            break;
        }

        if ($role_id !== null) {
            if ($role_id < 1) {
                echo 'no';
                break;
            }

            $edit_role = Role::find($role_id);

            if ($edit_role === null) {
                echo 'invalid role';
                break;
            }

            $app->getTemplating()->vars(compact('edit_role'));
        }

        echo $app->getTemplating()->render('@manage.users.roles_create');
        break;
}
