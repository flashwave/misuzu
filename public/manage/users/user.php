<?php
namespace Misuzu;

use Misuzu\Users\User;
use Misuzu\Users\UserNotFoundException;
use Misuzu\Users\UserRole;
use Misuzu\Users\UserRoleNotFoundException;

require_once '../../../misuzu.php';

if(!User::hasCurrent() || !perms_check_user(MSZ_PERMS_USER, User::getCurrent()->getId(), MSZ_PERM_USER_MANAGE_USERS)) {
    echo render_error(403);
    return;
}

$notices = [];
$userId = (int)filter_input(INPUT_GET, 'u', FILTER_SANITIZE_NUMBER_INT);
$currentUser = User::getCurrent();
$currentUserId = $currentUser->getId();

try {
    $userInfo = User::byId($userId);
} catch(UserNotFoundException $ex) {
    echo render_error(404);
    return;
}

$canEdit = $currentUser->hasAuthorityOver($userInfo);
$canEditPerms = $canEdit && perms_check_user(MSZ_PERMS_USER, $currentUserId, MSZ_PERM_USER_MANAGE_PERMS);
$permissions = manage_perms_list(perms_get_user_raw($userId));

if(CSRF::validateRequest() && $canEdit) {
    if(!empty($_POST['roles']) && is_array($_POST['roles']) && array_test($_POST['roles'], 'ctype_digit')) {
        // Fetch existing roles
        $existingRoles = $userInfo->getRoles();

        // Initialise set array with existing roles
        $setRoles = $existingRoles;

        // Read user input array and throw intval on em
        $applyRoles = array_apply($_POST['roles'], 'intval');

        // Storage array for roles to dump
        $removeRoles = [];

        // STEP 1: Check for roles to be removed in the existing set.
        //         Roles that the current users isn't allowed to touch (hierarchy) will stay.
        foreach($setRoles as $role) {
            // Also prevent the main role from being removed.
            if($role->isDefault() || !$currentUser->hasAuthorityOver($role))
                continue;
            if(!in_array($role->getId(), $applyRoles))
                $removeRoles[] = $role;
        }

        // STEP 2: Purge the ones marked for removal.
        $setRoles = array_diff($setRoles, $removeRoles);

        // STEP 3: Add roles to the set array from the user input, if the user has authority over the given roles.
        foreach($applyRoles as $roleId) {
            try {
                $role = $existingRoles[$roleId] ?? UserRole::byId($roleId);
            } catch(UserRoleNotFoundException $ex) {
                continue;
            }
            if(!$currentUser->hasAuthorityOver($role))
                continue;
            if(!in_array($role, $setRoles))
                $setRoles[] = $role;
        }

        foreach($removeRoles as $role)
            $userInfo->removeRole($role);

        foreach($setRoles as $role)
            $userInfo->addRole($role);
    }

    if(!empty($_POST['user']) && is_array($_POST['user'])) {
        $setUsername     = (string)($_POST['user']['username'] ?? '');
        $setEMailAddress = (string)($_POST['user']['email'] ?? '');
        $setCountry      = (string)($_POST['user']['country'] ?? '');
        $setTitle        = (string)($_POST['user']['title'] ?? '');

        $displayRole = (int)($_POST['user']['display_role'] ?? 0);

        try {
            $userInfo->setDisplayRole(UserRole::byId($displayRole));
        } catch(UserRoleNotFoundException $ex) {}

        $usernameValidation = User::validateUsername($setUsername);
        $emailValidation = User::validateEMailAddress($setEMailAddress);
        $countryValidation = strlen($setCountry) === 2
            && ctype_alpha($setCountry)
            && ctype_upper($setCountry);

        if(!empty($usernameValidation))
            $notices[] = User::usernameValidationErrorString($usernameValidation);

        if(!empty($emailValidation)) {
            $notices[] = $emailValidation === 'in-use'
                ? 'This e-mail address has already been used!'
                : 'This e-mail address is invalid!';
        }

        if(!$countryValidation)
            $notices[] = 'Country code was invalid.';

        if(strlen($setTitle) > 64)
            $notices[] = 'User title was invalid.';

        if(empty($notices))
            $userInfo->setUsername((string)($_POST['user']['username'] ?? ''))
                ->setEMailAddress((string)($_POST['user']['email'] ?? ''))
                ->setCountry((string)($_POST['user']['country'] ?? ''))
                ->setTitle((string)($_POST['user']['title'] ?? ''))
                ->setDisplayRole(UserRole::byId((int)($_POST['user']['display_role'] ?? 0)));
    }

    if(!empty($_POST['colour']) && is_array($_POST['colour'])) {
        $setColour = null;

        if(!empty($_POST['colour']['enable'])) {
            $setColour = new Colour;

            try {
                $setColour->setHex((string)($_POST['colour']['hex'] ?? ''));
            } catch(\Exception $ex) {
                $notices[] = $ex->getMessage();
            }
        }

        if(empty($notices))
            $userInfo->setColour($setColour);
    }

    if(!empty($_POST['password']) && is_array($_POST['password'])) {
        $passwordNewValue = (string)($_POST['password']['new'] ?? '');
        $passwordConfirmValue = (string)($_POST['password']['confirm'] ?? '');

        if(!empty($passwordNewValue)) {
            if($passwordNewValue !== $passwordConfirmValue)
                $notices[] = 'Confirm password does not match.';
            elseif(!empty(User::validatePassword($passwordNewValue)))
                $notices[] = 'New password is too weak.';
            else
                $userInfo->setPassword($passwordNewValue);
        }
    }

    if(empty($notices))
        $userInfo->save();

    if($canEditPerms && !empty($_POST['perms']) && is_array($_POST['perms'])) {
        $perms = manage_perms_apply($permissions, $_POST['perms']);

        if($perms !== null) {
            if(!perms_set_user_raw($userId, $perms))
                $notices[] = 'Failed to update permissions.';
        } else {
            if(!perms_delete_user($userId))
                $notices[] = 'Failed to remove permissions.';
        }

        // this smells, make it refresh/apply in a non-retarded way
        $permissions = manage_perms_list(perms_get_user_raw($userId));
    }
}

Template::render('manage.users.user', [
    'user_info' => $userInfo,
    'manage_notices' => $notices,
    'manage_roles' => UserRole::all(true),
    'can_edit_user' => $canEdit,
    'can_edit_perms' => $canEdit && $canEditPerms,
    'permissions' => $permissions ?? [],
]);
