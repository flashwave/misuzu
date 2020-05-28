<?php
namespace Misuzu;

use Misuzu\Net\IPAddress;
use Misuzu\Users\User;
use Misuzu\Users\UserLoginAttempt;
use Misuzu\Users\UserSession;
use Misuzu\Users\UserSessionCreationFailedException;
use Misuzu\Users\UserAuthSession;
use Misuzu\Users\UserAuthSessionNotFoundException;

require_once '../../misuzu.php';

if(UserSession::hasCurrent()) {
    url_redirect('index');
    return;
}

$twofactor = !empty($_POST['twofactor']) && is_array($_POST['twofactor']) ? $_POST['twofactor'] : [];
$notices = [];
$ipAddress = IPAddress::remote();
$remainingAttempts = UserLoginAttempt::remaining();

try {
    $tokenInfo = UserAuthSession::byToken(
        !empty($_GET['token']) && is_string($_GET['token']) ? $_GET['token'] : (
            !empty($twofactor['token']) && is_string($twofactor['token']) ? $twofactor['token'] : ''
        )
    );
} catch(UserAuthSessionNotFoundException $ex) {}

if(empty($tokenInfo) || $tokenInfo->hasExpired()) {
    url_redirect('auth-login');
    return;
}

$userInfo = $tokenInfo->getUser();

// checking user_totp_key specifically because there's a fringe chance that
//  there's a token present, but totp is actually disabled
if(!$userInfo->hasTOTP()) {
    url_redirect('auth-login');
    return;
}

while(!empty($twofactor)) {
    if(!CSRF::validateRequest()) {
        $notices[] = 'Was unable to verify the request, please try again!';
        break;
    }

    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $redirect = !empty($twofactor['redirect']) && is_string($twofactor['redirect']) ? $twofactor['redirect'] : '';

    if(empty($twofactor['code']) || !is_string($twofactor['code'])) {
        $notices[] = 'Code field was empty.';
        break;
    }

    if($remainingAttempts < 1) {
        $notices[] = 'There are too many failed login attempts from your IP address, please try again later.';
        break;
    }

    if(!in_array($twofactor['code'], $userInfo->getValidTOTPTokens())) {
        $notices[] = sprintf(
            "Invalid two factor code, %d attempt%s remaining",
            $remainingAttempts - 1,
            $remainingAttempts === 2 ? '' : 's'
        );
        UserLoginAttempt::create(false, $userInfo);
        break;
    }

    UserLoginAttempt::create(true, $userInfo);
    $tokenInfo->delete();

    try {
        $sessionInfo = UserSession::create($userInfo);
        $sessionInfo->setCurrent();
    } catch(UserSessionCreationFailedException $ex) {
        $notices[] = "Something broke while creating a session for you, please tell an administrator or developer about this!";
        break;
    }

    $authToken = AuthToken::create($userInfo, $sessionInfo);
    setcookie('msz_auth', $authToken->pack(), $sessionInfo->getExpiresTime(), '/', '.' . $_SERVER['HTTP_HOST'], !empty($_SERVER['HTTPS']), true);

    if(!is_local_url($redirect)) {
        $redirect = url('index');
    }

    redirect($redirect);
    return;
}

Template::render('auth.twofactor', [
    'twofactor_notices' => $notices,
    'twofactor_redirect' => !empty($_GET['redirect']) && is_string($_GET['redirect']) ? $_GET['redirect'] : url('index'),
    'twofactor_attempts_remaining' => $remainingAttempts,
    'twofactor_token' => $tokenInfo->getToken(),
]);
