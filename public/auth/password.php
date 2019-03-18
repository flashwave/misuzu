<?php
use Misuzu\Request\RequestVar;

require_once '../../misuzu.php';

if (user_session_active()) {
    header(sprintf('Location: %s', url('settings-mode', ['mode' => 'account'])));
    return;
}

$reset = RequestVar::post()->reset;
$forgot = RequestVar::post()->forgot;
$userId = $reset->user->value('int') ?? RequestVar::get()->user->value('int', 0);
$username = $userId > 0 ? user_username_from_id($userId) : '';

if ($userId > 0 && empty($username)) {
    header(sprintf('Location: %s', url('auth-forgot')));
    return;
}

$notices = [];
$siteIsPrivate = boolval(config_get_default(false, 'Private', 'enabled'));
$canResetPassword = $siteIsPrivate ? boolval(config_get_default(false, 'Private', 'password_reset')) : true;
$ipAddress = ip_remote_address();
$remainingAttempts = user_login_attempts_remaining($ipAddress);

while ($canResetPassword) {
    if (!empty($reset->value('array', null)) && $userId > 0) {
        if (!csrf_verify('passreset', $_POST['csrf'] ?? '')) {
            $notices[] = 'Was unable to verify the request, please try again!';
            break;
        }

        $verificationCode = $reset->verification->value('string', '');

        if (!user_recovery_token_validate($userId, $verificationCode)) {
            $notices[] = 'Invalid verification code!';
            break;
        }

        $passwordNew = $reset->password->new->value('string', '');
        $passwordConfirm = $reset->password->confirm->value('string', '');

        if (empty($passwordNew) || empty($passwordConfirm)
            || $passwordNew !== $passwordConfirm) {
            $notices[] = "Password confirmation failed!";
            break;
        }

        if (user_validate_password($passwordNew) !== '') {
            $notices[] = 'Your password is too weak!';
            break;
        }

        if (user_password_set($userId, $passwordNew)) {
            audit_log(MSZ_AUDIT_PASSWORD_RESET, $userId);
        } else {
            throw new UnexpectedValueException('Password reset failed.');
        }

        // disable two factor auth to prevent getting locked out of account entirely
        user_totp_update($userId, null);

        user_recovery_token_invalidate($userId, $verificationCode);

        header(sprintf('Location: %s', url('auth-login', ['redirect' => '/'])));
        return;
    }

    if (!empty($forgot->value('array', null))) {
        if (!csrf_verify('passforgot', $_POST['csrf'] ?? '')) {
            $notices[] = 'Was unable to verify the request, please try again!';
            break;
        }

        if ($forgot->email->empty()) {
            $notices[] = "You didn't supply an e-mail address.";
            break;
        }

        if ($remainingAttempts < 1) {
            $notices[] = "There are too many failed login attempts from your IP address, please try again later.";
            break;
        }

        $forgotUser = user_find_for_reset($forgot->email->value('string'));

        if (empty($forgotUser)) {
            $notices[] = "This e-mail address is not registered with us.";
            break;
        }

        if (!user_recovery_token_sent($forgotUser['user_id'], $ipAddress)) {
            $verificationCode = user_recovery_token_create($forgotUser['user_id'], $ipAddress);

            if (empty($verificationCode)) {
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

            if (!mail_send($message)) {
                $notices[] = "Failed to send reset email, please contact the administrator.";
                user_recovery_token_invalidate($forgotUser['user_id'], $verificationCode);
                break;
            }
        }

        header(sprintf('Location: %s', url('auth-reset', ['user' => $forgotUser['user_id']])));
        return;
    }

    break;
}

echo tpl_render($userId > 0 ? 'auth.password_reset' : 'auth.password_forgot', [
    'password_notices' => $notices,
    'password_email' => $forgot->email->value('string', ''),
    'password_attempts_remaining' => $remainingAttempts,
    'password_user_id' => $userId,
    'password_username' => $username,
    'password_verification' => $verificationCode ?? '',
]);
