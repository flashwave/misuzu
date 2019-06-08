<?php
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
$siteIsPrivate = boolval(config_get_default(false, 'Private', 'enabled'));
$loginPermission = $siteIsPrivate ? intval(config_get_default(0, 'Private', 'permission')) : 0;
$ipAddress = ip_remote_address();
$remainingAttempts = user_login_attempts_remaining($ipAddress);

while(!empty($_POST['login']) && is_array($_POST['login'])) {
    if(!csrf_verify('login', $_POST['csrf'] ?? '')) {
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

    $userData = user_find_for_login($_POST['login']['username']);
    $attemptsRemainingError = sprintf(
        "%d attempt%s remaining",
        $remainingAttempts - 1,
        $remainingAttempts === 2 ? '' : 's'
    );
    $loginFailedError = "Invalid username or password, {$attemptsRemainingError}.";

    if(empty($userData) || $userData['user_id'] < 1) {
        user_login_attempt_record(false, null, $ipAddress, $userAgent);
        $notices[] = $loginFailedError;
        break;
    }

    if(empty($userData['password'])) {
        $notices[] = 'Your password has been invalidated, please reset it.';
        break;
    }

    if(!is_null($userData['user_deleted']) || !password_verify($_POST['login']['password'], $userData['password'])) {
        user_login_attempt_record(false, $userData['user_id'], $ipAddress, $userAgent);
        $notices[] = $loginFailedError;
        break;
    }

    if(user_password_needs_rehash($userData['password'])) {
        user_password_set($userData['user_id'], $_POST['login']['password']);
    }

    if($loginPermission > 0 && !perms_check_user(MSZ_PERMS_GENERAL, $userData['user_id'], $loginPermission)) {
        $notices[] = "Login succeeded, but you're not allowed to browse the site right now.";
        user_login_attempt_record(true, $userData['user_id'], $ipAddress, $userAgent);
        break;
    }

    if($userData['totp_enabled']) {
        url_redirect('auth-two-factor', [
            'token' => user_auth_tfa_token_create($userData['user_id']),
        ]);
        return;
    }

    user_login_attempt_record(true, $userData['user_id'], $ipAddress, $userAgent);
    $sessionKey = user_session_create($userData['user_id'], $ipAddress, $userAgent);

    if(empty($sessionKey)) {
        $notices[] = "Something broke while creating a session for you, please tell an administrator or developer about this!";
        break;
    }

    user_session_start($userData['user_id'], $sessionKey);

    $cookieLife = strtotime(user_session_current('session_expires'));
    $cookieValue = base64url_encode(user_session_cookie_pack($userData['user_id'], $sessionKey));
    setcookie('msz_auth', $cookieValue, $cookieLife, '/', '', true, true);

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
$sitePrivateMessage = $siteIsPrivate ? config_get_default('', 'Private', 'message') : '';
$canResetPassword = $siteIsPrivate ? boolval(config_get_default(false, 'Private', 'password_reset')) : true;
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
