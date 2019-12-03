<?php
namespace Misuzu;

use Misuzu\Users\User;

require_once '../../misuzu.php';

if(user_session_active()) {
    url_redirect('index');
    return;
}

if(!empty($_GET['resolve_user']) && is_string($_GET['resolve_user'])) {
    header('Content-Type: text/plain; charset=utf-8');
    echo user_id_from_username($_GET['resolve_user']);
    return;
}

$notices = [];
$siteIsPrivate = Config::get('private.enable', Config::TYPE_BOOL);
$loginPermCat = $siteIsPrivate ? Config::get('private.perm.cat', Config::TYPE_STR) : '';
$loginPermVal = $siteIsPrivate ? Config::get('private.perm.val', Config::TYPE_INT) : 0;
$ipAddress = ip_remote_address();
$remainingAttempts = user_login_attempts_remaining($ipAddress);

while(!empty($_POST['login']) && is_array($_POST['login'])) {
    if(!csrf_verify_request()) {
        $notices[] = 'Was unable to verify the request, please try again!';
        break;
    }

    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $loginRedirect = empty($_POST['login']['redirect']) || !is_string($_POST['login']['redirect']) ? '' : $_POST['login']['redirect'];

    if(empty($_POST['login']['username']) || empty($_POST['login']['password'])
        || !is_string($_POST['login']['username']) || !is_string($_POST['login']['password'])) {
        $notices[] = "You didn't fill in a username and/or password.";
        break;
    }

    if($remainingAttempts < 1) {
        $notices[] = "There are too many failed login attempts from your IP address, please try again later.";
        break;
    }

    $userData = User::findForLogin($_POST['login']['username']);
    $attemptsRemainingError = sprintf(
        "%d attempt%s remaining",
        $remainingAttempts - 1,
        $remainingAttempts === 2 ? '' : 's'
    );
    $loginFailedError = "Invalid username or password, {$attemptsRemainingError}.";

    if(empty($userData)) {
        user_login_attempt_record(false, null, $ipAddress, $userAgent);
        $notices[] = $loginFailedError;
        break;
    }

    if(!$userData->hasPassword()) {
        $notices[] = 'Your password has been invalidated, please reset it.';
        break;
    }

    if($userData->isDeleted() || !$userData->checkPassword($_POST['login']['password'])) {
        user_login_attempt_record(false, $userData->user_id, $ipAddress, $userAgent);
        $notices[] = $loginFailedError;
        break;
    }

    if($userData->passwordNeedsRehash()) {
        $userData->setPassword($_POST['login']['password']);
    }

    if(!empty($loginPermCat) && $loginPermVal > 0 && !perms_check_user($loginPermCat, $userData['user_id'], $loginPermVal)) {
        $notices[] = "Login succeeded, but you're not allowed to browse the site right now.";
        user_login_attempt_record(true, $userData->user_id, $ipAddress, $userAgent);
        break;
    }

    if($userData->hasTOTP()) {
        url_redirect('auth-two-factor', [
            'token' => user_auth_tfa_token_create($userData->user_id),
        ]);
        return;
    }

    user_login_attempt_record(true, $userData->user_id, $ipAddress, $userAgent);
    $sessionKey = user_session_create($userData->user_id, $ipAddress, $userAgent);

    if(empty($sessionKey)) {
        $notices[] = "Something broke while creating a session for you, please tell an administrator or developer about this!";
        break;
    }

    user_session_start($userData->user_id, $sessionKey);

    $cookieLife = strtotime(user_session_current('session_expires'));
    $cookieValue = Base64::encode(user_session_cookie_pack($userData->user_id, $sessionKey), true);
    setcookie('msz_auth', $cookieValue, $cookieLife, '/', '', !empty($_SERVER['HTTPS']), true);

    if(!is_local_url($loginRedirect)) {
        $loginRedirect = url('index');
    }

    redirect($loginRedirect);
    return;
}

$welcomeMode = !empty($_GET['welcome']);
$loginUsername = !empty($_POST['login']['username']) && is_string($_POST['login']['username']) ? $_POST['login']['username'] : (
    !empty($_GET['username']) && is_string($_GET['username']) ? $_GET['username'] : ''
);
$loginRedirect = $welcomeMode ? url('index') : (!empty($_GET['redirect']) && is_string($_GET['redirect']) ? $_GET['redirect'] : null) ?? $_SERVER['HTTP_REFERER'] ?? url('index');
$sitePrivateMessage = $siteIsPrivate ? Config::get('private.msg', Config::TYPE_STR) : '';
$canResetPassword = $siteIsPrivate ? Config::get('private.allow_password_reset', Config::TYPE_BOOL, true) : true;
$canRegisterAccount = !$siteIsPrivate;

echo tpl_render('auth.login', [
    'login_notices' => $notices,
    'login_username' => $loginUsername,
    'login_redirect' => $loginRedirect,
    'login_can_reset_password' => $canResetPassword,
    'login_can_register' => $canRegisterAccount,
    'login_attempts_remaining' => $remainingAttempts,
    'login_welcome' => $welcomeMode,
    'login_private' => [
        'enabled' => $siteIsPrivate,
        'message' => $sitePrivateMessage,
    ],
]);
