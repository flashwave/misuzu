<?php
use Misuzu\Request\RequestVar;

require_once '../../misuzu.php';

if (user_session_active()) {
    header(sprintf('Location: %s', url('index')));
    return;
}

$twofactor = RequestVar::post()->twofactor;
$notices = [];
$ipAddress = ip_remote_address();
$remainingAttempts = user_login_attempts_remaining($ipAddress);
$tokenInfo = user_auth_tfa_token_info(
    RequestVar::get()->token->string() ?? $twofactor->token->string('')
);

// checking user_totp_key specifically because there's a fringe chance that
//  there's a token present, but totp is actually disabled
if (empty($tokenInfo['user_totp_key'])) {
    header(sprintf('Location: %s', url('auth-login')));
    return;
}

while (!empty($twofactor->value('array'))) {
    if (!csrf_verify('twofactor', $_POST['csrf'] ?? '')) {
        $notices[] = 'Was unable to verify the request, please try again!';
        break;
    }

    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $redirect = $twofactor->redirect->string('');

    if ($twofactor->code->empty()) {
        $notices[] = 'Code field was empty.';
        break;
    }

    if ($remainingAttempts < 1) {
        $notices[] = 'There are too many failed login attempts from your IP address, please try again later.';
        break;
    }

    $givenCode = $twofactor->code->string('');
    $currentCode = totp_generate($tokenInfo['user_totp_key']);
    $previousCode = totp_generate($tokenInfo['user_totp_key'], time() - 30);

    if ($currentCode !== $givenCode && $previousCode !== $givenCode) {
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

    if (empty($sessionKey)) {
        $notices[] = "Something broke while creating a session for you, please tell an administrator or developer about this!";
        break;
    }

    user_auth_tfa_token_invalidate($tokenInfo['tfa_token']);
    user_session_start($tokenInfo['user_id'], $sessionKey);

    $cookieLife = strtotime(user_session_current('session_expires'));
    $cookieValue = base64url_encode(user_session_cookie_pack($tokenInfo['user_id'], $sessionKey));
    setcookie('msz_auth', $cookieValue, $cookieLife, '/', '', true, true);

    if (!is_local_url($redirect)) {
        $redirect = url('index');
    }

    header("Location: {$redirect}");
    return;
}

echo tpl_render('auth.twofactor', [
    'twofactor_notices' => $notices,
    'twofactor_redirect' => RequestVar::get()->redirect->string() ?? url('index'),
    'twofactor_attempts_remaining' => $remainingAttempts,
    'twofactor_token' => $tokenInfo['tfa_token'],
]);
