<?php
require_once '../../misuzu.php';

if(!user_session_active()) {
    echo render_error(401);
    return;
}

$errors = [];
$currentUserId = user_session_current('user_id');
$currentEmail = user_email_get($currentUserId);
$isRestricted = user_warning_check_restriction($currentUserId);
$twoFactorInfo = user_totp_info($currentUserId);
$isVerifiedRequest = csrf_verify_request();

if(!$isRestricted && $isVerifiedRequest && !empty($_POST['role'])) {
    $roleId = (int)($_POST['role']['id'] ?? 0);

    if($roleId > 0 && user_role_has($currentUserId, $roleId)) {
        switch ($_POST['role']['mode'] ?? '') {
            case 'display':
                user_role_set_display($currentUserId, $roleId);
                break;

            case 'leave':
                if (user_role_can_leave($roleId)) {
                    user_role_remove($currentUserId, $roleId);
                } else {
                    $errors[] = "You're not allow to leave this role, an administrator has to remove it for you.";
                }
                break;
        }
    } else {
        $errors[] = "You're trying to modify a role that hasn't been assigned to you.";
    }
}

if($isVerifiedRequest && isset($_POST['tfa']['enable']) && (bool)$twoFactorInfo['totp_enabled'] !== (bool)$_POST['tfa']['enable']) {
    if((bool)$_POST['tfa']['enable']) {
        $tfaKey = totp_generate_key();

        tpl_vars([
            'settings_2fa_code' => $tfaKey,
            'settings_2fa_image' => totp_qrcode(totp_uri(
                sprintf(
                    '%s:%s',
                    config_get_default('Misuzu', 'Site', 'name'),
                    $twoFactorInfo['username']
                ),
                $tfaKey,
                $_SERVER['HTTP_HOST']
            )),
        ]);

        user_totp_update($currentUserId, $tfaKey);
    } else {
        user_totp_update($currentUserId, null);
    }

    $twoFactorInfo['totp_enabled'] = !$twoFactorInfo['totp_enabled'];
}

if($isVerifiedRequest && !empty($_POST['current_password'])) {
    if(!user_password_verify_db($currentUserId, $_POST['current_password'] ?? '')) {
        $errors[] = 'Your password was incorrect.';
    } else {
        // Changing e-mail
        if(!empty($_POST['email']['new'])) {
            if(empty($_POST['email']['confirm']) || $_POST['email']['new'] !== $_POST['email']['confirm']) {
                $errors[] = 'The addresses you entered did not match each other.';
            } elseif($currentEmail === mb_strtolower($_POST['email']['confirm'])) {
                $errors[] = 'This is already your e-mail address!';
            } else {
                $checkMail = user_validate_email($_POST['email']['new'], true);

                if ($checkMail !== '') {
                    switch ($checkMail) {
                        case 'dns':
                            $errors[] = 'No valid MX record exists for this domain.';
                            break;

                        case 'format':
                            $errors[] = 'The given e-mail address was incorrectly formatted.';
                            break;

                        case 'in-use':
                            $errors[] = 'This e-mail address is already in use.';
                            break;

                        default:
                            $errors[] = 'Unknown e-mail validation error.';
                    }
                } else {
                    user_email_set($currentUserId, $_POST['email']['new']);
                    audit_log(MSZ_AUDIT_PERSONAL_EMAIL_CHANGE, $currentUserId, [
                        $_POST['email']['new'],
                    ]);
                }
            }
        }

        // Changing password
        if(!empty($_POST['password']['new'])) {
            if(empty($_POST['password']['confirm']) || $_POST['password']['new'] !== $_POST['password']['confirm']) {
                $errors[] = 'The new passwords you entered did not match each other.';
            } else {
                $checkPassword = user_validate_password($_POST['password']['new']);

                if($checkPassword !== '') {
                    $errors[] = 'The given passwords was too weak.';
                } else {
                    user_password_set($currentUserId, $_POST['password']['new']);
                    audit_log(MSZ_AUDIT_PERSONAL_PASSWORD_CHANGE, $currentUserId);
                }
            }
        }
    }
}

$userRoles = user_role_all_user($currentUserId);

echo tpl_render('settings.account', [
    'errors' => $errors,
    'current_email' => $currentEmail,
    'user_roles' => $userRoles,
    'user_display_role' => user_role_get_display($currentUserId),
    'is_restricted' => $isRestricted,
    'settings_2fa_enabled' => $twoFactorInfo['totp_enabled'],
]);
