<?php
// TODO: UNFUCK THIS FILE
namespace Misuzu;

use Misuzu\Users\User;

require_once '../../../misuzu.php';

if(!User::hasCurrent() || !perms_check_user(MSZ_PERMS_USER, User::getCurrent()->getId(), MSZ_PERM_USER_MANAGE_ROLES)) {
    echo render_error(403);
    return;
}

$roleId = $_GET['r'] ?? null;
$currentUserId = User::getCurrent()->getId();
/*$isSuperUser = user_check_super($currentUserId);
$canEdit = $isSuperUser || user_check_authority($currentUserId, $userId);*/
$canEditPerms = /*$canEdit && */perms_check_user(MSZ_PERMS_USER, $currentUserId, MSZ_PERM_USER_MANAGE_PERMS);

if($canEditPerms) {
    $permissions = manage_perms_list(perms_get_role_raw($roleId ?? 0));
}

if(!empty($_POST['role']) && is_array($_POST['role']) && CSRF::validateRequest()) {
    $roleHierarchy = (int)($_POST['role']['hierarchy'] ?? -1);

    if(!user_check_super($currentUserId) && ($roleId === null
            ? (user_get_hierarchy($currentUserId) <= $roleHierarchy)
            : !user_role_check_authority($currentUserId, $roleId))) {
        echo 'Your hierarchy is too low to do this.';
        return;
    }

    $roleName = $_POST['role']['name'] ?? '';
    $roleNameLength = strlen($roleName);

    if($roleNameLength < 1 || $roleNameLength > 255) {
        echo 'invalid name length';
        return;
    }

    $roleSecret = !empty($_POST['role']['secret']);

    if($roleHierarchy < 1 || $roleHierarchy > 100) {
        echo 'Invalid hierarchy value.';
        return;
    }

    $roleColour = new Colour;

    if(!empty($_POST['role']['colour']['inherit'])) {
        $roleColour->setInherit(true);
    } else {
        foreach(['red', 'green', 'blue'] as $key) {
            $value = (int)($_POST['role']['colour'][$key] ?? -1);

            try {
               $roleColour->{'set' . ucfirst($key)}($value);
            } catch(\Exception $ex){
                echo $ex->getMessage();
                return;
            }
        }
    }

    $roleDescription = $_POST['role']['description'] ?? null;
    $roleTitle = $_POST['role']['title'] ?? null;

    if($roleDescription !== null) {
        $rdLength = strlen($roleDescription);

        if($rdLength < 1) {
            $roleDescription = null;
        } elseif($rdLength > 1000) {
            echo 'description is too long';
            return;
        }
    }

    if($roleTitle !== null) {
        $rtLength = strlen($roleTitle);

        if($rtLength < 1) {
            $roleTitle = null;
        } elseif($rtLength > 64) {
            echo 'title is too long';
            return;
        }
    }

    if($roleId < 1) {
        $updateRole = DB::prepare('
            INSERT INTO `msz_roles`
                (
                    `role_name`, `role_hierarchy`, `role_hidden`, `role_colour`,
                    `role_description`, `role_title`
                )
            VALUES
                (
                    :role_name, :role_hierarchy, :role_hidden, :role_colour,
                    :role_description, :role_title
                )
        ');
    } else {
        $updateRole = DB::prepare('
            UPDATE `msz_roles`
            SET `role_name` = :role_name,
                `role_hierarchy` = :role_hierarchy,
                `role_hidden` = :role_hidden,
                `role_colour` = :role_colour,
                `role_description` = :role_description,
                `role_title` = :role_title
            WHERE `role_id` = :role_id
        ');
        $updateRole->bind('role_id', $roleId);
    }

    $updateRole->bind('role_name', $roleName);
    $updateRole->bind('role_hierarchy', $roleHierarchy);
    $updateRole->bind('role_hidden', $roleSecret ? 1 : 0);
    $updateRole->bind('role_colour', $roleColour->getRaw());
    $updateRole->bind('role_description', $roleDescription);
    $updateRole->bind('role_title', $roleTitle);
    $updateRole->execute();

    if($roleId < 1) {
        $roleId = DB::lastId();
    }

    if(!empty($permissions) && !empty($_POST['perms']) && is_array($_POST['perms'])) {
        $perms = manage_perms_apply($permissions, $_POST['perms']);

        if($perms !== null) {
            $permKeys = array_keys($perms);
            $setPermissions = DB::prepare('
                REPLACE INTO `msz_permissions`
                    (`role_id`, `user_id`, `' . implode('`, `', $permKeys) . '`)
                VALUES
                    (:role_id, NULL, :' . implode(', :', $permKeys) . ')
            ');
            $setPermissions->bind('role_id', $roleId);

            foreach($perms as $key => $value) {
                $setPermissions->bind($key, $value);
            }

            $setPermissions->execute();
        } else {
            $deletePermissions = DB::prepare('
                DELETE FROM `msz_permissions`
                WHERE `role_id` = :role_id
                AND `user_id` IS NULL
            ');
            $deletePermissions->bind('role_id', $roleId);
            $deletePermissions->execute();
        }
    }

    url_redirect('manage-role', ['role' => $roleId]);
    return;
}

if($roleId !== null) {
    if($roleId < 1) {
        echo 'no';
        return;
    }

    $getEditRole = DB::prepare('
        SELECT *
        FROM `msz_roles`
        WHERE `role_id` = :role_id
    ');
    $getEditRole->bind('role_id', $roleId);
    $editRole = $getEditRole->fetch();

    if(empty($editRole)) {
        echo 'invalid role';
        return;
    }

    Template::set([
        'edit_role' => $editRole,
        'role_colour' => new Colour($editRole['role_colour']),
    ]);
}

Template::render('manage.users.role', [
    'can_manage_perms' => $canEditPerms,
    'permissions' => $permissions ?? [],
]);
