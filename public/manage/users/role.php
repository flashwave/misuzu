<?php
namespace Misuzu;

use Misuzu\Users\User;
use Misuzu\Users\UserRole;
use Misuzu\Users\UserRoleNotFoundException;

require_once '../../../misuzu.php';

if(!User::hasCurrent() || !perms_check_user(MSZ_PERMS_USER, User::getCurrent()->getId(), MSZ_PERM_USER_MANAGE_ROLES)) {
    echo render_error(403);
    return;
}

$roleId = (int)filter_input(INPUT_GET, 'r', FILTER_SANITIZE_NUMBER_INT);

if($roleId > 0)
    try {
        $roleInfo = UserRole::byId($roleId);
    } catch(UserRoleNotFoundException $ex) {
        echo render_error(404);
        return;
    }

$currentUser = User::getCurrent();
$currentUserId = $currentUser->getId();
$canEditPerms = perms_check_user(MSZ_PERMS_USER, $currentUserId, MSZ_PERM_USER_MANAGE_PERMS);

if($canEditPerms)
    $permissions = manage_perms_list(perms_get_role_raw($roleId ?? 0));

if(!empty($_POST['role']) && is_array($_POST['role']) && CSRF::validateRequest()) {
    $roleHierarchy = (int)($_POST['role']['hierarchy'] ?? -1);

    if(!$currentUser->isSuper() && (isset($roleInfo) ? $roleInfo->hasAuthorityOver($currentUser) : $currentUser->getRank() <= $roleHierarchy)) {
        echo 'You don\'t hold authority over this role.';
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

    $roleDescription = $_POST['role']['description'] ?? '';
    $roleTitle = $_POST['role']['title'] ?? '';

    if($roleDescription !== null) {
        $rdLength = strlen($roleDescription);

        if($rdLength > 1000) {
            echo 'description is too long';
            return;
        }
    }

    if($roleTitle !== null) {
        $rtLength = strlen($roleTitle);

        if($rtLength > 64) {
            echo 'title is too long';
            return;
        }
    }

    if(!isset($roleInfo))
        $roleInfo = new UserRole;

    $roleInfo->setName($roleName)
        ->setRank($roleHierarchy)
        ->setHidden($roleSecret)
        ->setColour($roleColour)
        ->setDescription($roleDescription)
        ->setTitle($roleTitle)
        ->save();

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
            $setPermissions->bind('role_id', $roleInfo->getId());

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
            $deletePermissions->bind('role_id', $roleInfo->getId());
            $deletePermissions->execute();
        }
    }

    url_redirect('manage-role', ['role' => $roleInfo->getId()]);
    return;
}

Template::render('manage.users.role', [
    'role_info' => $roleInfo ?? null,
    'can_manage_perms' => $canEditPerms,
    'permissions' => $permissions ?? [],
]);
