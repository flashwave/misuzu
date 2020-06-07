<?php
namespace Misuzu;

use Misuzu\AuditLog;
use Misuzu\Users\User;
use Misuzu\Users\UserNotFoundException;
use Misuzu\Users\UserLoginAttempt;
use Misuzu\Users\UserRecoveryToken;
use Misuzu\Users\UserRecoveryTokenNotFoundException;
use Misuzu\Users\UserRecoveryTokenCreationFailedException;
use Misuzu\Users\UserSession;

require_once '../../misuzu.php';

if(UserSession::hasCurrent()) {
    url_redirect('settings-account');
    return;
}

$reset = !empty($_POST['reset']) && is_array($_POST['reset']) ? $_POST['reset'] : [];
$forgot = !empty($_POST['forgot']) && is_array($_POST['forgot']) ? $_POST['forgot'] : [];
$userId = !empty($reset['user']) ? (int)$reset['user'] : (
    !empty($_GET['user']) ? (int)$_GET['user'] : 0
);

if($userId > 0)
    try {
        $userInfo = User::byId($userId);
    } catch(UserNotFoundException $ex) {
        url_redirect('auth-forgot');
        return;
    }

$notices = [];
$siteIsPrivate = Config::get('private.enable', Config::TYPE_BOOL);
$canResetPassword = $siteIsPrivate ? Config::get('private.allow_password_reset', Config::TYPE_BOOL, true) : true;
$remainingAttempts = UserLoginAttempt::remaining();

while($canResetPassword) {
    if(!empty($reset) && $userId > 0) {
        if(!CSRF::validateRequest()) {
            $notices[] = 'Was unable to verify the request, please try again!';
            break;
        }

        $verificationCode = !empty($reset['verification']) && is_string($reset['verification']) ? $reset['verification'] : '';

        try {
            $tokenInfo = UserRecoveryToken::byToken($verificationCode);
        } catch(UserRecoveryTokenNotFoundException $ex) {
            unset($tokenInfo);
        }

        if(empty($tokenInfo) || !$tokenInfo->isValid() || $tokenInfo->getUserId() !== $userInfo->getId()) {
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

        if(User::validatePassword($passwordNew) !== '') {
            $notices[] = 'Your password is too weak!';
            break;
        }

        // also disables two factor auth to prevent getting locked out of account entirely
        // this behaviour should really be replaced with recovery keys...
        $userInfo->setPassword($passwordNew)
            ->removeTOTPKey()
            ->save();

        AuditLog::create(AuditLog::PASSWORD_RESET, [], $userInfo);

        $tokenInfo->invalidate();

        url_redirect('auth-login', ['redirect' => '/']);
        return;
    }

    if(!empty($forgot)) {
        if(!CSRF::validateRequest()) {
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

        try {
            $forgotUser = User::byEMailAddress($forgot['email']);
        } catch(UserNotFoundException $ex) {
            unset($forgotUser);
        }

        if(empty($forgotUser) || $forgotUser->isDeleted()) {
            $notices[] = "This e-mail address is not registered with us.";
            break;
        }

        try {
            $tokenInfo = UserRecoveryToken::byUserAndRemoteAddress($forgotUser);
        } catch(UserRecoveryTokenNotFoundException $ex) {
            $tokenInfo = UserRecoveryToken::create($forgotUser);

            $recoveryMessage = Mailer::template('password-recovery', [
                'username' => $forgotUser->getUsername(),
                'token' => $tokenInfo->getToken(),
            ]);

            $recoveryMail = Mailer::sendMessage(
                [$forgotUser->getEMailAddress() => $forgotUser->getUsername()],
                $recoveryMessage['subject'], $recoveryMessage['message']
            );

            if(!$recoveryMail) {
                $notices[] = "Failed to send reset email, please contact the administrator.";
                $tokenInfo->invalidate();
                break;
            }
        }

        url_redirect('auth-reset', ['user' => $forgotUser->getId()]);
        return;
    }

    break;
}

Template::render(isset($userInfo) ? 'auth.password_reset' : 'auth.password_forgot', [
    'password_notices' => $notices,
    'password_email' => !empty($forget['email']) && is_string($forget['email']) ? $forget['email'] : '',
    'password_attempts_remaining' => $remainingAttempts,
    'password_user' => $userInfo ?? null,
    'password_verification' => $verificationCode ?? '',
]);
