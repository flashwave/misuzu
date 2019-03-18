<?php
use Misuzu\Request\RequestVar;

require_once '../../misuzu.php';

if (user_session_active()) {
    header(sprintf('Location: %s', url('index')));
    return;
}

$register = RequestVar::post()->register;
$notices = [];
$ipAddress = ip_remote_address();
$remainingAttempts = user_login_attempts_remaining($ipAddress);
$restricted = ip_blacklist_check(ip_remote_address()) ? 'blacklist'
    : (user_warning_check_ip(ip_remote_address()) ? 'ban' : '');

while (!$restricted && !empty($register->value('array'))) {
    if (!csrf_verify('register', $_POST['csrf'] ?? '')) {
        $notices[] = 'Was unable to verify the request, please try again!';
        break;
    }

    if ($remainingAttempts < 1) {
        $notices[] = "There are too many failed login attempts from your IP address, you may not create an account right now.";
        break;
    }

    if ($register->username->empty() || $register->password->empty() || $register->email->empty() || $register->question->empty()) {
        $notices[] = "You haven't filled in all fields.";
        break;
    }

    $checkSpamBot = mb_strtolower($register->question->string(''));
    $spamBotValid = [
        '19', '21', 'nineteen', 'nine-teen', 'nine teen', 'twentyone', 'twenty-one', 'twenty one',
    ];

    if (!in_array($checkSpamBot, $spamBotValid)) {
        $notices[] = 'Human only cool club, robots begone.';
        break;
    }

    $username = $register->username->string('');
    $usernameValidation = user_validate_username($username, true);
    if ($usernameValidation !== '') {
        $notices[] = MSZ_USER_USERNAME_VALIDATION_STRINGS[$usernameValidation];
    }

    $email = $register->email->string('');
    $emailValidation = user_validate_email($email, true);
    if ($emailValidation !== '') {
        $notices[] = $emailValidation === 'in-use'
            ? 'This e-mail address has already been used!'
            : 'The e-mail address you entered is invalid!';
    }

    $password = $register->password->string('');
    if (user_validate_password($password) !== '') {
        $notices[] = 'Your password is too weak!';
    }

    if (!empty($notices)) {
        break;
    }

    $createUser = user_create(
        $username,
        $password,
        $email,
        $ipAddress
    );

    if ($createUser < 1) {
        $notices[] = 'Something went wrong while creating your account, please alert an administrator or a developer about this!';
        break;
    }

    user_role_add($createUser, MSZ_ROLE_MAIN);
    header(sprintf('Location: %s', url('auth-login-welcome', ['username' => $username])));
    return;
}

echo tpl_render('auth.register', [
    'register_notices' => $notices,
    'register_username' => $register->username->string(''),
    'register_email' => $register->email->string(''),
    'register_restricted' => $restricted,
]);
