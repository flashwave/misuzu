<?php
namespace Misuzu;

require_once '../../misuzu.php';

if(user_session_active()) {
    url_redirect('index');
    return;
}

$twofactor = !empty($_POST['twofactor']) && is_array($_POST['twofactor']) ? $_POST['twofactor'] : [];
$notices = [];
$ipAddress = ip_remote_address();
$remainingAttempts = user_login_attempts_remaining($ipAddress);
$tokenInfo = user_auth_tfa_token_info(
    !empty($_GET['token']) && is_string($_GET['token']) ? $_GET['token'] : (
        !empty($twofactor['token']) && is_string($twofactor['token']) ? $twofactor['token'] : ''
    )
);

// checking user_totp_key specifically because there's a fringe chance that
//  there's a token present, but totp is actually disabled
if(empty($tokenInfo['user_totp_key'])) {
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

    $totp = new TOTP($tokenInfo['user_totp_key']);
    $accepted = [
        $totp->generate(time()),
        $totp->generate(time() - 30),
        $totp->generate(time() + 30),
    ];

    if(!in_array($twofactor['code'], $acceptedCodes)) {
        $notices[] = sprintf(
            "Invalid two factor code, %d attempt%s remaining",
            $remainingAttempts - 1,
            $remainingAttempts === 2 ? '' : 's'
        );
        user_login_attempt_record(false, $tokenInfo['user_id'], $ipAddress, $userAgent);
        break;
    }

    user_login_attempt_record(true, $tokenInfo['user_id'], $ipAddress, $userAgent);
    $sessionKey = user_session_create($tokenInfo['user_id'], $ipAddress, $userAgent);

    if(empty($sessionKey)) {
        $notices[] = "Something broke while creating a session for you, please tell an administrator or developer about this!";
        break;
    }

    user_auth_tfa_token_invalidate($tokenInfo['tfa_token']);
    user_session_start($tokenInfo['user_id'], $sessionKey);

    $cookieLife = strtotime(user_session_current('session_expires'));
    $cookieValue = Base64::encode(user_session_cookie_pack($tokenInfo['user_id'], $sessionKey), true);
    setcookie('msz_auth', $cookieValue, $cookieLife, '/', '', !empty($_SERVER['HTTPS']), true);

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
    'twofactor_token' => $tokenInfo['tfa_token'],
]);
