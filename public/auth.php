<?php
$isSubmission = !empty($_POST['auth']) && is_array($_POST['auth']);
$authMode = $isSubmission ? ($_POST['auth']['mode'] ?? '') : ($_GET['m'] ?? 'login');
$misuzuBypassLockdown = $authMode === 'login' || $authMode === 'get_user';

require_once '../misuzu.php';

$siteIsPrivate = boolval(config_get_default(false, 'Private', 'enabled'));
$loginPermission = $siteIsPrivate ? intval(config_get_default(0, 'Private', 'permission')) : 0;
$canResetPassword = $siteIsPrivate ? boolval(config_get_default(false, 'Private', 'password_reset')) : true;
$canCreateAccount = !$siteIsPrivate && !boolval(config_get_default(false, 'Auth', 'lockdown'));

$authUsername = $isSubmission ? ($_POST['auth']['username'] ?? '') : ($_GET['username'] ?? '');
$authEmail = $isSubmission ? ($_POST['auth']['email'] ?? '') : ($_GET['email'] ?? '');
$authPassword = $_POST['auth']['password'] ?? '';
$authVerification = $_POST['auth']['verification'] ?? '';
$authRedirect = $_POST['auth']['redirect'] ?? $_GET['redirect'] ?? $_SERVER['HTTP_REFERER'] ?? '/';
$authRestricted = ip_blacklist_check(ip_remote_address())
    ? 1
    : (
        user_warning_check_ip(ip_remote_address())
            ? 2
            : 0
    );

tpl_vars([
    'can_create_account' => $canCreateAccount,
    'can_reset_password' => $canResetPassword,
    'auth_mode' => $authMode,
    'auth_username' => $authUsername,
    'auth_email' => $authEmail,
    'auth_redirect' => $authRedirect,
    'auth_restricted' => $authRestricted,
]);

switch ($authMode) {
    case 'get_user':
        echo user_id_from_username($_GET['u'] ?? '');
        break;

    case 'logout':
        if (!user_session_active()) {
            header(sprintf('Location: %s', url('index')));
            return;
        }

        if (csrf_verify('logout', $_GET['s'] ?? '')) {
            setcookie('msz_auth', '', -3600, '/', '', true, true);
            user_session_stop(true);
            header(sprintf('Location: %s', url('index')));
            return;
        }

        echo tpl_render('auth.logout');
        break;

    case 'reset':
        // If we're logged in, redirect to the password/e-mail change part in settings instead.
        if (user_session_active()) {
            header(sprintf('Location: %s', url('settings-mode', ['mode' => 'account'])));
            break;
        }

        if (!$canResetPassword) {
            header(sprintf('Location: %s', url('index')));
            return;
        }

        $resetUserId = (int)($_POST['user'] ?? $_GET['u'] ?? 0);

        if (empty($resetUserId)) {
            header(sprintf('Location: %s', url('auth-forgot')));
            break;
        }

        $resetUsername = user_username_from_id($resetUserId);

        if (empty($resetUsername)) {
            header(sprintf('Location: %s', url('auth-login')));
            break;
        }

        tpl_var('auth_reset_message', "A verification code should've been sent to your e-mail address.");

        while ($isSubmission) {
            if (!csrf_verify('passreset', $_POST['csrf'] ?? '')) {
                tpl_var('auth_reset_error', 'Possible request forgery detected, refresh and try again.');
                break;
            }

            if (!user_recovery_token_validate($resetUserId, $authVerification)) {
                tpl_var('auth_reset_error', 'Invalid verification code!');
                break;
            }

            tpl_var('reset_verify', $authVerification);

            if (empty($authPassword['new'])
                || empty($authPassword['confirm'])
                || $authPassword['new'] !== $authPassword['confirm']) {
                tpl_var('auth_reset_error', 'Your passwords didn\'t match!');
                break;
            }

            if (user_validate_password($authPassword['new']) !== '') {
                tpl_var('auth_reset_error', 'Your password is too weak!');
                break;
            }

            if (user_password_set($resetUserId, $authPassword['new'])) {
                audit_log(MSZ_AUDIT_PASSWORD_RESET, $resetUserId);
            } else {
                throw new UnexpectedValueException('Password reset failed.');
            }

            user_recovery_token_invalidate($resetUserId, $authVerification);

            header(sprintf('Location: %s', url('auth-login')));
            break;
        }

        echo tpl_render('auth.password', [
            'reset_user' => [
                'user_id' => $resetUserId,
                'username' => $resetUsername,
            ],
        ]);
        break;

    case 'forgot':
        if (user_session_active() || !$canResetPassword) {
            header(sprintf('Location: %s', url('index')));
            break;
        }

        while ($isSubmission) {
            if (!csrf_verify('passforgot', $_POST['csrf'] ?? '')) {
                tpl_var('auth_forgot_error', 'Possible request forgery detected, refresh and try again.');
                break;
            }

            if (empty($authEmail)) {
                tpl_var('auth_forgot_error', 'Please enter an e-mail address.');
                break;
            }

            $forgotUser = user_find_for_reset($authEmail);

            if (empty($forgotUser)) {
                tpl_var('auth_forgot_error', 'This user is not registered with us.');
                break;
            }

            $ipAddress = ip_remote_address();

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
                    tpl_var('auth_forgot_error', 'Failed to send reset email, please contact the administrator.');
                    user_recovery_token_invalidate($forgotUser['user_id'], $verificationCode);
                    break;
                }
            }

            header(sprintf('Location: %s', url('auth-reset', ['user' => $forgotUser['user_id']])));
            break;
        }

        echo tpl_render('auth.auth');
        break;

    case 'login':
        if (user_session_active()) {
            header('Location: ' . url('index'));
            break;
        }

        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $authLoginError = '';

        while ($isSubmission) {
            $ipAddress = ip_remote_address();

            if (!isset($authUsername, $authPassword)) {
                $authLoginError = "You didn't fill all the forms!";
                break;
            }

            $remainingAttempts = user_login_attempts_remaining($ipAddress);

            if ($remainingAttempts < 1) {
                $authLoginError = 'Too many failed login attempts, try again later.';
                break;
            }

            if (!csrf_verify('login', $_POST['csrf'] ?? '')) {
                $authLoginError = 'Possible request forgery detected, refresh and try again.';
                break;
            }

            $userData = user_find_for_login($authUsername);

            $loginFailedError = sprintf(
                "Invalid username or password, %d attempt%s remaining.",
                $remainingAttempts - 1,
                $remainingAttempts === 2 ? '' : 's'
            );

            if (empty($userData) || $userData['user_id'] < 1) {
                user_login_attempt_record(false, null, $ipAddress, $userAgent);
                $authLoginError = $loginFailedError;
                break;
            }

            if (!password_verify($authPassword, $userData['password'])) {
                user_login_attempt_record(false, $userData['user_id'], $ipAddress, $userAgent);
                $authLoginError = $loginFailedError;
                break;
            }

            user_login_attempt_record(true, $userData['user_id'], $ipAddress, $userAgent);

            if ($loginPermission > 0) {
                $generalPerms = perms_get_user(MSZ_PERMS_GENERAL, $userData['user_id']);

                if (!perms_check($generalPerms, $loginPermission)) {
                    $authLoginError = 'Your credentials were correct, but your account lacks the proper permissions to use this website.';
                    break;
                }
            }

            $sessionKey = user_session_create($userData['user_id'], $ipAddress, $userAgent);

            if ($sessionKey === '') {
                $authLoginError = 'Unable to create new session, contact an administrator ASAP.';
                break;
            }

            user_session_start($userData['user_id'], $sessionKey);
            $cookieLife = strtotime(user_session_current('session_expires'));
            $cookieValue = base64_encode(user_session_cookie_pack($userData['user_id'], $sessionKey));
            setcookie('msz_auth', $cookieValue, $cookieLife, '/', '', true, true);

            if (!is_local_url($authRedirect)) {
                $authRedirect = url('index');
            }

            header("Location: {$authRedirect}");
            return;
        }

        if (!empty($authLoginError)) {
            tpl_var('auth_login_error', $authLoginError);
        } elseif ($siteIsPrivate) {
            tpl_var('auth_register_message', config_get_default('', 'Private', 'message'));
        }

        echo tpl_render('auth.auth');
        break;

    case 'register':
        if (user_session_active()) {
            header('Location: ' . url('index'));
        }

        $authRegistrationError = '';

        while ($isSubmission) {
            if (!$canCreateAccount || $authRestricted) {
                $authRegistrationError = 'You may not create an account right now.';
                break;
            }

            if (!isset($authUsername, $authPassword, $authEmail)) {
                $authRegistrationError = "You didn't fill all the forms!";
                break;
            }

            if (!csrf_verify('register', $_POST['csrf'] ?? '')) {
                $authRegistrationError = 'Possible request forgery detected, refresh and try again.';
                break;
            }

            $checkSpamBot = mb_strtolower($_POST['auth']['meow'] ?? '');
            $spamBotValid = [
                '19', '21', 'nineteen', 'nine-teen', 'nine teen', 'twentyone', 'twenty-one', 'twenty one',
            ];

            if (!in_array($checkSpamBot, $spamBotValid)) {
                $authRegistrationError = 'Human only cool club, robots begone.';
                break;
            }

            $usernameValidation = user_validate_username($authUsername, true);
            if ($usernameValidation !== '') {
                $authRegistrationError = MSZ_USER_USERNAME_VALIDATION_STRINGS[$usernameValidation];
                break;
            }

            $emailValidation = user_validate_email($authEmail, true);
            if ($emailValidation !== '') {
                $authRegistrationError = $emailValidation === 'in-use'
                    ? 'This e-mail address has already been used!'
                    : 'The e-mail address you entered is invalid!';
                break;
            }

            if (user_validate_password($authPassword) !== '') {
                $authRegistrationError = 'Your password is too weak!';
                break;
            }

            $createUser = user_create(
                $authUsername,
                $authPassword,
                $authEmail,
                ip_remote_address()
            );

            if ($createUser < 1) {
                $authRegistrationError = 'Something happened?';
                break;
            }

            user_role_add($createUser, MSZ_ROLE_MAIN);

            tpl_var('auth_register_message', 'Welcome to Flashii! You may now log in.');
            break;
        }

        if (!empty($authRegistrationError)) {
            tpl_var('auth_register_error', $authRegistrationError);
        }

        echo tpl_render('auth.auth');
        break;
}
