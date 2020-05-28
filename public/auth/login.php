<?php
namespace Misuzu;

use Misuzu\AuthToken;
use Misuzu\Net\IPAddress;
use Misuzu\Users\User;
use Misuzu\Users\UserNotFoundException;
use Misuzu\Users\UserAuthSession;
use Misuzu\Users\UserLoginAttempt;
use Misuzu\Users\UserSession;
use Misuzu\Users\UserSessionCreationFailedException;

require_once '../../misuzu.php';

if(UserSession::hasCurrent()) {
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
$remainingAttempts = UserLoginAttempt::remaining();

while(!empty($_POST['login']) && is_array($_POST['login'])) {
    if(!CSRF::validateRequest()) {
        $notices[] = 'Was unable to verify the request, please try again!';
        break;
    }

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

    $attemptsRemainingError = sprintf(
        "%d attempt%s remaining",
        $remainingAttempts - 1,
        $remainingAttempts === 2 ? '' : 's'
    );
    $loginFailedError = "Invalid username or password, {$attemptsRemainingError}.";

    try {
        $userInfo = User::findForLogin($_POST['login']['username']);
    } catch(UserNotFoundException $ex) {
        UserLoginAttempt::create(false);
        $notices[] = $loginFailedError;
        break;
    }

    if(!$userInfo->hasPassword()) {
        $notices[] = 'Your password has been invalidated, please reset it.';
        break;
    }

    if($userInfo->isDeleted() || !$userInfo->checkPassword($_POST['login']['password'])) {
        UserLoginAttempt::create(false, $userInfo);
        $notices[] = $loginFailedError;
        break;
    }

    if($userInfo->passwordNeedsRehash()) {
        $userInfo->setPassword($_POST['login']['password']);
    }

    if(!empty($loginPermCat) && $loginPermVal > 0 && !perms_check_user($loginPermCat, $userInfo->getId(), $loginPermVal)) {
        $notices[] = "Login succeeded, but you're not allowed to browse the site right now.";
        UserLoginAttempt::create(true, $userInfo);
        break;
    }

    if($userInfo->hasTOTP()) {
        url_redirect('auth-two-factor', [
            'token' => UserAuthSession::create($userInfo)->getToken(),
        ]);
        return;
    }

    UserLoginAttempt::create(true, $userInfo);

    try {
        $sessionInfo = UserSession::create($userInfo);
        $sessionInfo->setCurrent();
    } catch(UserSessionCreationFailedException $ex) {
        $notices[] = "Something broke while creating a session for you, please tell an administrator or developer about this!";
        break;
    }

    $authToken = AuthToken::create($userInfo, $sessionInfo);
    setcookie('msz_auth', $authToken->pack(), $sessionInfo->getExpiresTime(), '/', '.' . $_SERVER['HTTP_HOST'], !empty($_SERVER['HTTPS']), true);

    if(!is_local_url($loginRedirect))
        $loginRedirect = url('index');

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

Template::render('auth.login', [
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
