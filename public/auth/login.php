<?php
use Misuzu\Request\RequestVar;

require_once '../../misuzu.php';

if (user_session_active()) {
    header(sprintf('Location: %s', url('index')));
    return;
}

if (isset(RequestVar::get()->resolve_user)) {
    header('Content-Type: text/plain; charset=utf-8');
    echo user_id_from_username(RequestVar::get()->resolve_user->value('string'));
    return;
}

$login = RequestVar::post()->login;
$notices = [];
$siteIsPrivate = boolval(config_get_default(false, 'Private', 'enabled'));
$loginPermission = $siteIsPrivate ? intval(config_get_default(0, 'Private', 'permission')) : 0;
$ipAddress = ip_remote_address();
$remainingAttempts = user_login_attempts_remaining($ipAddress);

while (!empty($login->value('array'))) {
    if (!csrf_verify('login', $_POST['csrf'] ?? '')) {
        $notices[] = 'Was unable to verify the request, please try again!';
        break;
    }

    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    if ($login->username->empty() || $login->password->empty()) {
        $notices[] = "You didn't fill in a username and/or password.";
        break;
    }

    if ($remainingAttempts < 1) {
        $notices[] = "There are too many failed login attempts from your IP address, please try again later.";
        break;
    }

    $userData = user_find_for_login($login->username->value('string', ''));
    $loginFailedError = sprintf(
        "Invalid username or password, %d attempt%s remaining.",
        $remainingAttempts - 1,
        $remainingAttempts === 2 ? '' : 's'
    );

    if (empty($userData) || $userData['user_id'] < 1) {
        user_login_attempt_record(false, null, $ipAddress, $userAgent);
        $notices[] = $loginFailedError;
        break;
    }

    if (!password_verify($login->password->value('string', ''), $userData['password'])) {
        user_login_attempt_record(false, $userData['user_id'], $ipAddress, $userAgent);
        $notices[] = $loginFailedError;
        break;
    }

    user_login_attempt_record(true, $userData['user_id'], $ipAddress, $userAgent);

    if ($loginPermission > 0 && !perms_check_user(MSZ_PERMS_GENERAL, $userData['user_id'], $loginPermission)) {
        $notices[] = "Login succeeded, but you're not allowed to browse the site right now.";
        break;
    }

    $sessionKey = user_session_create($userData['user_id'], $ipAddress, $userAgent);

    if (empty($sessionKey)) {
        $notices[] = "Something broke while creating a session for you, please tell an administrator or developer about this!";
        break;
    }

    user_session_start($userData['user_id'], $sessionKey);
    $cookieLife = strtotime(user_session_current('session_expires'));
    $cookieValue = base64url_encode(user_session_cookie_pack($userData['user_id'], $sessionKey));
    setcookie('msz_auth', $cookieValue, $cookieLife, '/', '', true, true);

    $redirect = $login->redirect->value('string', '');

    if (!is_local_url($redirect)) {
        $redirect = url('index');
    }

    header("Location: {$redirect}");
    return;
}

$welcomeMode = RequestVar::get()->welcome->value('bool', false);
$loginUsername = $login->username->value('string') ?? RequestVar::get()->username->value('string', '');
$loginRedirect = $welcomeMode ? '/' : RequestVar::get()->redirect->value('string') ?? $_SERVER['HTTP_REFERER'] ?? '/';
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
