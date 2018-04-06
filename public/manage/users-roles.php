<?php
use Misuzu\Application;
use Misuzu\Colour;
use Misuzu\Users\Role;

require_once __DIR__ . '/../../misuzu.php';

$role_mode = (string)($_GET['m'] ?? 'list');
$role_id = (int)($_GET['i'] ?? 0);

while ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!tmp_csrf_verify($_POST['csrf'] ?? '')) {
        echo 'csrf err';
        break;
    }

    if (!in_array($role_mode, ['create', 'edit'], true)) {
        echo 'invalid mode';
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

    header('Location: ?m=list');
    break;
}

switch ($role_mode) {
    case 'list':
        $users_page = (int)($_GET['p'] ?? 1);
        $manage_roles = Role::paginate(32, ['*'], 'p', $users_page);
        $app->templating->vars(compact('manage_roles'));

        echo $app->templating->render('@manage.users.roles');
        break;

    case 'edit':
        if (!isset($edit_role)) {
            if ($role_id < 1) {
                echo 'no';
                break;
            }

            $edit_role = Role::find($role_id);
        }

        if ($edit_role === null) {
            echo 'invalid role';
            break;
        }

        $app->templating->vars(compact('edit_role'));
        // no break

    case 'create':
        echo $app->templating->render('@manage.users.roles_create');
        break;
}
