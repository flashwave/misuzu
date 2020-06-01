<?php
namespace Misuzu;

use Misuzu\Net\IPAddress;
use Misuzu\Net\IPAddressBlacklist;
use Misuzu\Users\User;
use Misuzu\Users\UserLoginAttempt;
use Misuzu\Users\UserSession;
use Misuzu\Users\UserWarning;

require_once '../../misuzu.php';

if(UserSession::hasCurrent()) {
    url_redirect('index');
    return;
}

$register = !empty($_POST['register']) && is_array($_POST['register']) ? $_POST['register'] : [];
$notices = [];
$ipAddress = IPAddress::remote();
$remainingAttempts = UserLoginAttempt::remaining();
$restricted = IPAddressBlacklist::check($ipAddress) ? 'blacklist'
    : (UserWarning::countByRemoteAddress() > 0 ? 'ban' : '');

while(!$restricted && !empty($register)) {
    if(!CSRF::validateRequest()) {
        $notices[] = 'Was unable to verify the request, please try again!';
        break;
    }

    if($remainingAttempts < 1) {
        $notices[] = "There are too many failed login attempts from your IP address, you may not create an account right now.";
        break;
    }

    if(empty($register['username']) || empty($register['password']) || empty($register['email']) || empty($register['question'])
        || !is_string($register['username']) || !is_string($register['password']) || !is_string($register['email']) || !is_string($register['question'])) {
        $notices[] = "You haven't filled in all fields.";
        break;
    }

    $checkSpamBot = mb_strtolower($register['question']);
    $spamBotValid = [
        '19', '21', 'nineteen', 'nine-teen', 'nine teen', 'twentyone', 'twenty-one', 'twenty one',
    ];

    if(!in_array($checkSpamBot, $spamBotValid)) {
        $notices[] = 'Human only cool club, robots begone.';
        break;
    }

    $usernameValidation = User::validateUsername($register['username']);
    if($usernameValidation !== '') {
        $notices[] = User::usernameValidationErrorString($usernameValidation);
    }

    $emailValidation = User::validateEMailAddress($register['email']);
    if($emailValidation !== '') {
        $notices[] = $emailValidation === 'in-use'
            ? 'This e-mail address has already been used!'
            : 'The e-mail address you entered is invalid!';
    }

    if($register['password_confirm'] !== $register['password']) {
        $notices[] = 'The given passwords don\'t match.';
    }

    if(User::validatePassword($register['password']) !== '') {
        $notices[] = 'Your password is too weak!';
    }

    if(!empty($notices)) {
        break;
    }

    $createUser = User::create(
        $register['username'],
        $register['password'],
        $register['email'],
        $ipAddress
    );

    if($createUser === null) {
        $notices[] = 'Something went wrong while creating your account, please alert an administrator or a developer about this!';
        break;
    }

    user_role_add($createUser->getId(), MSZ_ROLE_MAIN);
    url_redirect('auth-login-welcome', ['username' => $createUser->getUsername()]);
    return;
}

Template::render('auth.register', [
    'register_notices' => $notices,
    'register_username' => !empty($register['username']) && is_string($register['username']) ? $register['username'] : '',
    'register_email' => !empty($register['email']) && is_string($register['email']) ? $register['email'] : '',
    'register_restricted' => $restricted,
]);
