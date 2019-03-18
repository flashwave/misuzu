<?php
require_once '../../misuzu.php';

if (user_session_active()) {
    header(sprintf('Location: %s', url('index')));
    return;
}

$register = !empty($_POST['register']) && is_array($_POST['register']) ? $_POST['register'] : [];
$notices = [];
$ipAddress = ip_remote_address();
$remainingAttempts = user_login_attempts_remaining($ipAddress);
$restricted = ip_blacklist_check(ip_remote_address()) ? 'blacklist'
    : (user_warning_check_ip(ip_remote_address()) ? 'ban' : '');

while (!$restricted && !empty($register)) {
    if (!csrf_verify('register', $_POST['csrf'] ?? '')) {
        $notices[] = 'Was unable to verify the request, please try again!';
        break;
    }

    if ($remainingAttempts < 1) {
        $notices[] = "There are too many failed login attempts from your IP address, you may not create an account right now.";
        break;
    }

    if (empty($register['username']) || empty($register['password']) || empty($register['email']) || empty($register['question'])
        || !is_string($register['username']) || !is_string($register['password']) || !is_string($register['email']) || !is_string($register['question'])) {
        $notices[] = "You haven't filled in all fields.";
        break;
    }

    $checkSpamBot = mb_strtolower($register['question']);
    $spamBotValid = [
        '19', '21', 'nineteen', 'nine-teen', 'nine teen', 'twentyone', 'twenty-one', 'twenty one',
    ];

    if (!in_array($checkSpamBot, $spamBotValid)) {
        $notices[] = 'Human only cool club, robots begone.';
        break;
    }

    $usernameValidation = user_validate_username($register['username'], true);
    if ($usernameValidation !== '') {
        $notices[] = MSZ_USER_USERNAME_VALIDATION_STRINGS[$usernameValidation];
    }

    $emailValidation = user_validate_email($register['email'], true);
    if ($emailValidation !== '') {
        $notices[] = $emailValidation === 'in-use'
            ? 'This e-mail address has already been used!'
            : 'The e-mail address you entered is invalid!';
    }

    if (user_validate_password($register['password']) !== '') {
        $notices[] = 'Your password is too weak!';
    }

    if (!empty($notices)) {
        break;
    }

    $createUser = user_create(
        $register['username'],
        $register['password'],
        $register['email'],
        $ipAddress
    );

    if ($createUser < 1) {
        $notices[] = 'Something went wrong while creating your account, please alert an administrator or a developer about this!';
        break;
    }

    user_role_add($createUser, MSZ_ROLE_MAIN);
    header(sprintf('Location: %s', url('auth-login-welcome', ['username' => $register['username']])));
    return;
}

echo tpl_render('auth.register', [
    'register_notices' => $notices,
    'register_username' => !empty($register['username']) && is_string($register['username']) ? $register['username'] : '',
    'register_email' => !empty($register['email']) && is_string($register['email']) ? $register['email'] : '',
    'register_restricted' => $restricted,
]);
