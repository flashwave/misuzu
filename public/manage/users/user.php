<?php
namespace Misuzu;

require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_USER, user_session_current('user_id'), MSZ_PERM_USER_MANAGE_USERS)) {
    echo render_error(403);
    return;
}

$notices = [];
$userId = (int)($_GET['u'] ?? 0);
$currentUserId = user_session_current('user_id');

if($userId < 1) {
    echo render_error(404);
    return;
}

$isSuperUser = user_check_super($currentUserId);
$canEdit = $isSuperUser || user_check_authority($currentUserId, $userId);
$canEditPerms = $canEdit && perms_check_user(MSZ_PERMS_USER, $currentUserId, MSZ_PERM_USER_MANAGE_PERMS);
$permissions = manage_perms_list(perms_get_user_raw($userId));

if(csrf_verify_request() && $canEdit) {
    if(!empty($_POST['roles']) && is_array($_POST['roles']) && array_test($_POST['roles'], 'ctype_digit')) {
        // Fetch existing roles
        $existingRoles = DB::prepare('
            SELECT `role_id`
            FROM `msz_user_roles`
            WHERE `user_id` = :user_id
        ');
        $existingRoles->bind('user_id', $userId);
        $existingRoles = $existingRoles->fetchAll();

        // Initialise set array with existing role ids
        $setRoles = array_column($existingRoles, 'role_id');

        // Read user input array and throw intval on em
        $applyRoles = array_apply($_POST['roles'], 'intval');

        // Storage array for roles to dump
        $removeRoles = [];

        // STEP 1: Check for roles to be removed in the existing set.
        //         Roles that the current users isn't allowed to touch (hierarchy) will stay.
        foreach($setRoles as $role) {
            // Also prevent the main role from being removed.
            if($role === MSZ_ROLE_MAIN || (!$isSuperUser && !user_role_check_authority($currentUserId, $role))) {
                continue;
            }

            if(!in_array($role, $applyRoles)) {
                $removeRoles[] = $role;
            }
        }

        // STEP 2: Purge the ones marked for removal.
        $setRoles = array_diff($setRoles, $removeRoles);

        // STEP 3: Add roles to the set array from the user input, if the user has authority over the given roles.
        foreach($applyRoles as $role) {
            if(!$isSuperUser && !user_role_check_authority($currentUserId, $role)) {
                continue;
            }

            if(!in_array($role, $setRoles)) {
                $setRoles[] = $role;
            }
        }

        if(!empty($setRoles)) {
            // The implode here probably sets off alarm bells, but the array is
            // guaranteed to only contain integers so it's probably fine.
            $removeRanks = DB::prepare(sprintf('
                DELETE FROM `msz_user_roles`
                WHERE `user_id` = :user_id
                AND `role_id` NOT IN (%s)
            ', implode(',', $setRoles)));
            $removeRanks->bind('user_id', $userId);
            $removeRanks->execute();

            $addRank = DB::prepare('
                INSERT IGNORE INTO `msz_user_roles`
                    (`user_id`, `role_id`)
                VALUES
                    (:user_id, :role_id)
            ');
            $addRank->bind('user_id', $userId);

            foreach($setRoles as $role) {
                $addRank->bind('role_id', $role);
                $addRank->execute();
            }
        }
    }

    $setUserInfo = [];

    if(!empty($_POST['user']) && is_array($_POST['user'])) {
        $setUserInfo['username'] = (string)($_POST['user']['username'] ?? '');
        $setUserInfo['email'] = (string)($_POST['user']['email'] ?? '');
        $setUserInfo['user_country'] = (string)($_POST['user']['country'] ?? '');
        $setUserInfo['user_title'] = (string)($_POST['user']['title'] ?? '');

        $displayRole = (int)($_POST['user']['display_role'] ?? 0);

        if(user_role_has($userId, $displayRole)) {
            $setUserInfo['display_role'] = $displayRole;
        }

        $usernameValidation = user_validate_username($setUserInfo['username']);
        $emailValidation = user_validate_email($setUserInfo['email']);
        $countryValidation = strlen($setUserInfo['user_country']) === 2
            && ctype_alpha($setUserInfo['user_country'])
            && ctype_upper($setUserInfo['user_country']);

        if(!empty($usernameValidation)) {
            $notices[] = MSZ_USER_USERNAME_VALIDATION_STRINGS[$usernameValidation];
        }

        if(!empty($emailValidation)) {
            $notices[] = $emailValidation === 'in-use'
                ? 'This e-mail address has already been used!'
                : 'This e-mail address is invalid!';
        } else {
            $setUserInfo['email'] = mb_strtolower($setUserInfo['email']);
        }

        if(!$countryValidation) {
            $notices[] = 'Country code was invalid.';
        }

        if(strlen($setUserInfo['user_title']) < 1) {
            $setUserInfo['user_title'] = null;
        } elseif(strlen($setUserInfo['user_title']) > 64) {
            $notices[] = 'User title was invalid.';
        }
    }

    if(!empty($_POST['colour']) && is_array($_POST['colour'])) {
        $userColour = null;

        if(!empty($_POST['colour']['enable'])) {
            $userColour = colour_create();

            if(!colour_from_hex($userColour, (string)($_POST['colour']['hex'] ?? ''))) {
                $notices[] = 'An invalid colour was supplied.';
            }
        }

        $setUserInfo['user_colour'] = $userColour;
    }

    if(!empty($_POST['password']) && is_array($_POST['password'])) {
        $passwordNewValue = (string)($_POST['password']['new'] ?? '');
        $passwordConfirmValue = (string)($_POST['password']['confirm'] ?? '');

        if(!empty($passwordNewValue)) {
            if($passwordNewValue !== $passwordConfirmValue) {
                $notices[] = 'Confirm password does not match.';
            } elseif(!empty(user_validate_password($passwordNewValue))) {
                $notices[] = 'New password is too weak.';
            } else {
                $setUserInfo['password'] = user_password_hash($passwordNewValue);
            }
        }
    }

    if(empty($notices) && !empty($setUserInfo)) {
        $userUpdate = DB::prepare(sprintf(
            '
                UPDATE `msz_users`
                SET %s
                WHERE `user_id` = :set_user_id
            ',
            pdo_prepare_array_update($setUserInfo, true)
        ));
        $userUpdate->bind('set_user_id', $userId);

        foreach($setUserInfo as $key => $value) {
            $userUpdate->bind($key, $value);
        }

        if(!$userUpdate->execute()) {
            $notices[] = 'Something went wrong while updating the user.';
        }
    }

    if($canEditPerms && !empty($_POST['perms']) && is_array($_POST['perms'])) {
        $perms = manage_perms_apply($permissions, $_POST['perms']);

        if($perms !== null) {
            if(!perms_set_user_raw($userId, $perms)) {
                $notices[] = 'Failed to update permissions.';
            }
        } else {
            if(!perms_delete_user($userId)) {
                $notices[] = 'Failed to remove permissions.';
            }
        }

        // this smells, make it refresh/apply in a non-retarded way
        $permissions = manage_perms_list(perms_get_user_raw($userId));
    }
}

$getUser = DB::prepare('
    SELECT
        u.*,
        INET6_NTOA(u.`register_ip`) as `register_ip_decoded`,
        INET6_NTOA(u.`last_ip`) as `last_ip_decoded`,
        COALESCE(u.`user_colour`, r.`role_colour`) as `colour`
    FROM `msz_users` as u
    LEFT JOIN `msz_roles` as r
    ON u.`display_role` = r.`role_id`
    WHERE `user_id` = :user_id
    ORDER BY `user_id`
');
$getUser->bind('user_id', $userId);
$manageUser = $getUser->fetch();

if(empty($manageUser)) {
    echo render_error(404);
    return;
}

$getRoles = DB::prepare('
    SELECT
        r.`role_id`, r.`role_name`, r.`role_hierarchy`, r.`role_colour`,
        (
            SELECT COUNT(`user_id`) > 0
            FROM `msz_user_roles`
            WHERE `role_id` = r.`role_id`
            AND `user_id` = :user_id
        ) AS `has_role`
    FROM `msz_roles` AS r
');
$getRoles->bind('user_id', $manageUser['user_id']);
$roles = $getRoles->fetchAll();

echo tpl_render('manage.users.user', [
    'manage_user' => $manageUser,
    'manage_notices' => $notices,
    'manage_roles' => $roles,
    'can_edit_user' => $canEdit,
    'can_edit_perms' => $canEdit && $canEditPerms,
    'permissions' => $permissions ?? [],
]);
