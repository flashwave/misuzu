<?php
require_once '../../misuzu.php';

if(user_session_active()) {
    url_redirect('settings-account');
    return;
}

$reset = !empty($_POST['reset']) && is_array($_POST['reset']) ? $_POST['reset'] : [];
$forgot = !empty($_POST['forgot']) && is_array($_POST['forgot']) ? $_POST['forgot'] : [];
$userId = !empty($reset['user']) ? (int)$reset['user'] : (
    !empty($_GET['user']) ? (int)$_GET['user'] : 0
);
$username = $userId > 0 ? user_username_from_id($userId) : '';

if($userId > 0 && empty($username)) {
    url_redirect('auth-forgot');
    return;
}

$notices = [];
$siteIsPrivate = config_get('private.enable', MSZ_CFG_BOOL);
$canResetPassword = $siteIsPrivate ? config_get('private.allow_password_reset', MSZ_CFG_BOOL, true) : true;
$ipAddress = ip_remote_address();
$remainingAttempts = user_login_attempts_remaining($ipAddress);

while($canResetPassword) {
    if(!empty($reset) && $userId > 0) {
        if(!csrf_verify_request()) {
            $notices[] = 'Was unable to verify the request, please try again!';
            break;
        }

        $verificationCode = !empty($reset['verification']) && is_string($reset['verification']) ? $reset['verification'] : '';

        if(!user_recovery_token_validate($userId, $verificationCode)) {
            $notices[] = 'Invalid verification code!';
            break;
        }

        $password = !empty($reset['password']) && is_array($reset['password']) ? $reset['password'] : [];
        $passwordNew = !empty($password['new']) && is_string($password['new']) ? $password['new'] : '';
        $passwordConfirm = !empty($password['confirm']) && is_string($password['confirm']) ? $password['confirm'] : '';

        if(empty($passwordNew) || empty($passwordConfirm)
            || $passwordNew !== $passwordConfirm) {
            $notices[] = "Password confirmation failed!";
            break;
        }

        if(user_validate_password($passwordNew) !== '') {
            $notices[] = 'Your password is too weak!';
            break;
        }

        if(user_password_set($userId, $passwordNew)) {
            audit_log(MSZ_AUDIT_PASSWORD_RESET, $userId);
        } else {
            throw new UnexpectedValueException('Password reset failed.');
        }

        // disable two factor auth to prevent getting locked out of account entirely
        user_totp_update($userId, null);

        user_recovery_token_invalidate($userId, $verificationCode);

        url_redirect('auth-login', ['redirect' => '/']);
        return;
    }

    if(!empty($forgot)) {
        if(!csrf_verify_request()) {
            $notices[] = 'Was unable to verify the request, please try again!';
            break;
        }

        if(empty($forgot['email']) || !is_string($forgot['email'])) {
            $notices[] = "You didn't supply an e-mail address.";
            break;
        }

        if($remainingAttempts < 1) {
            $notices[] = "There are too many failed login attempts from your IP address, please try again later.";
            break;
        }

        $forgotUser = user_find_for_reset($forgot['email']);

        if(empty($forgotUser)) {
            $notices[] = "This e-mail address is not registered with us.";
            break;
        }

        if(!user_recovery_token_sent($forgotUser['user_id'], $ipAddress)) {
            $verificationCode = user_recovery_token_create($forgotUser['user_id'], $ipAddress);

            if(empty($verificationCode)) {
                throw new UnexpectedValueException('A verification code failed to insert.');
            }

            $messageBody = <<<MSG
Hey {$forgotUser['username']},

You, or someone pretending to be you, has requested a password reset for your account.

Your verification code is: {$verificationCode}

If you weren't the person who requested this reset, please send a reply to this e-mail.
MSG;

            $message = mail_compose(
                [$forgotUser['email'] => $forgotUser['username']],
                'Flashii Password Reset',
                $messageBody
            );

            if(!mail_send($message)) {
                $notices[] = "Failed to send reset email, please contact the administrator.";
                user_recovery_token_invalidate($forgotUser['user_id'], $verificationCode);
                break;
            }
        }

        url_redirect('auth-reset', ['user' => $forgotUser['user_id']]);
        return;
    }

    break;
}

echo tpl_render($userId > 0 ? 'auth.password_reset' : 'auth.password_forgot', [
    'password_notices' => $notices,
    'password_email' => !empty($forget['email']) && is_string($forget['email']) ? $forget['email'] : '',
    'password_attempts_remaining' => $remainingAttempts,
    'password_user_id' => $userId,
    'password_username' => $username,
    'password_verification' => $verificationCode ?? '',
]);
