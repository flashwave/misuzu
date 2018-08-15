<?php
use Carbon\Carbon;
use Misuzu\Application;
use Misuzu\Database;
use Misuzu\Net\IPAddress;
use Misuzu\Users\Session;

require_once __DIR__ . '/../misuzu.php';

$config = $app->getConfig();

$usernameValidationErrors = [
    'trim' => 'Your username may not start or end with spaces!',
    'short' => sprintf('Your username is too short, it has to be at least %d characters!', MSZ_USERNAME_MIN_LENGTH),
    'long' => sprintf("Your username is too long, it can't be longer than %d characters!", MSZ_USERNAME_MAX_LENGTH),
    'double-spaces' => "Your username can't contain double spaces.",
    'invalid' => 'Your username contains invalid characters.',
    'spacing' => 'Please use either underscores or spaces, not both!',
    'in-use' => 'This username is already taken!',
];

$authMode = $_GET['m'] ?? 'login';
$preventRegistration = $config->get('Auth', 'prevent_registration', 'bool', false);

tpl_vars([
    'prevent_registration' => $preventRegistration,
    'auth_mode' => $authMode,
    'auth_username' => $_REQUEST['username'] ?? '',
    'auth_email' => $_REQUEST['email'] ?? '',
]);

switch ($authMode) {
    case 'logout':
        if (!$app->hasActiveSession()) {
            header('Location: /');
            return;
        }

        if (isset($_GET['s']) && tmp_csrf_verify($_GET['s'])) {
            set_cookie_m('uid', '', -3600);
            set_cookie_m('sid', '', -3600);
            user_session_delete($app->getSessionId());
            header('Location: /');
            return;
        }

        echo tpl_render('auth.logout');
        break;

    case 'reset':
        if ($app->hasActiveSession()) {
            header('Location: /settings.php');
            break;
        }

        $resetUser = (int)($_POST['user'] ?? $_GET['u'] ?? 0);
        $getResetUser = Database::prepare('
            SELECT `user_id`, `username`
            FROM `msz_users`
            WHERE `user_id` = :user_id
        ');
        $getResetUser->bindValue('user_id', $resetUser);
        $resetUser = $getResetUser->execute() ? $getResetUser->fetch(PDO::FETCH_ASSOC) : [];

        if (empty($resetUser)) {
            header('Location: ?m=forgot');
            break;
        }

        tpl_var('auth_reset_message', 'A verification code should\'ve been sent to your e-mail address.');

        while ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $validateRequest = Database::prepare('
                SELECT COUNT(`user_id`) > 0
                FROM `msz_users_password_resets`
                WHERE `user_id` = :user
                AND `verification_code` = :code
                AND `verification_code` IS NOT NULL
                AND `reset_requested` > NOW() - INTERVAL 1 HOUR
            ');
            $validateRequest->bindValue('user', $resetUser['user_id']);
            $validateRequest->bindValue('code', $_POST['verification'] ?? '');
            $validateRequest = $validateRequest->execute()
                ? (bool)$validateRequest->fetchColumn()
                : false;

            if (!$validateRequest) {
                tpl_var('auth_reset_error', 'Invalid verification code!');
                break;
            }

            tpl_var('reset_verify', $_POST['verification']);

            if (empty($_POST['password']['new'])
                || empty($_POST['password']['confirm'])
                || $_POST['password']['new'] !== $_POST['password']['confirm']) {
                tpl_var('auth_reset_error', 'Your passwords didn\'t match!');
                break;
            }

            if (user_validate_password($_POST['password']['new']) !== '') {
                tpl_var('auth_reset_error', 'Your password is too weak!');
                break;
            }

            $updatePassword = Database::prepare('
                UPDATE `msz_users`
                SET `password` = :password
                WHERE `user_id` = :user
            ');
            $updatePassword->bindValue('user', $resetUser['user_id']);
            $updatePassword->bindValue('password', user_password_hash($_POST['password']['new']));

            if ($updatePassword->execute()) {
                audit_log('PASSWORD_RESET', $resetUser['user_id']);
            } else {
                throw new UnexpectedValueException('Password reset failed.');
            }

            $invalidateCode = Database::prepare('
                UPDATE `msz_users_password_resets`
                SET `verification_code` = NULL
                WHERE `verification_code` = :code
                AND `user_id` = :user
            ');
            $invalidateCode->bindValue('user', $resetUser['user_id']);
            $invalidateCode->bindValue('code', $_POST['verification']);

            if (!$invalidateCode->execute()) {
                throw new UnexpectedValueException('Verification code invalidation failed.');
            }

            header('Location: /auth.php?m=login');
            break;
        }

        echo tpl_render('auth.password', [
            'reset_user' => $resetUser,
        ]);
        break;

    case 'forgot':
        if ($app->hasActiveSession()) {
            header('Location: /');
            break;
        }

        while ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (empty($_POST['email'])) {
                tpl_var('auth_forgot_error', 'Please enter an e-mail address.');
                break;
            }

            $forgotUser = Database::prepare('
                SELECT `user_id`, `username`, `email`
                FROM `msz_users`
                WHERE LOWER(`email`) = LOWER(:email)
            ');
            $forgotUser->bindValue('email', $_POST['email']);
            $forgotUser = $forgotUser->execute() ? $forgotUser->fetch(PDO::FETCH_ASSOC) : [];

            if (empty($forgotUser)) {
                break;
            }

            $ipAddress = IPAddress::remote()->getString();
            $emailSent = Database::prepare('
                SELECT COUNT(`verification_code`) > 0
                FROM `msz_users_password_resets`
                WHERE `user_id` = :user
                AND `reset_ip` = INET6_ATON(:ip)
                AND `reset_requested` > NOW() - INTERVAL 1 HOUR
                AND `verification_code` IS NOT NULL
            ');
            $emailSent->bindValue('user', $forgotUser['user_id']);
            $emailSent->bindValue('ip', $ipAddress);
            $emailSent = $emailSent->execute()
                ? (bool)$emailSent->fetchColumn()
                : false;

            if (!$emailSent) {
                $verificationCode = bin2hex(random_bytes(6));
                $insertResetKey = Database::prepare('
                    REPLACE INTO `msz_users_password_resets`
                        (`user_id`, `reset_ip`, `verification_code`)
                    VALUES
                        (:user, INET6_ATON(:ip), :code)
                ');
                $insertResetKey->bindValue('user', $forgotUser['user_id']);
                $insertResetKey->bindValue('ip', $ipAddress);
                $insertResetKey->bindValue('code', $verificationCode);

                if (!$insertResetKey->execute()) {
                    throw new UnexpectedValueException('A verification code failed to insert.');
                }

                $messageBody = <<<MSG
Hey {$forgotUser['username']},

You, or someone pretending to be you, has requested a password reset for your account.

Your verification code is: {$verificationCode}

If you weren't the person who requested this reset, please send a reply to this e-mail.
MSG;

                $message = (new Swift_Message('Flashii Password Reset'))
                    ->setFrom([
                        $config->get('Mail', 'sender_email', 'string', 'sys@misuzu.lh') =>
                        $config->get('Mail', 'sender_name', 'string', 'Misuzu')
                    ])
                    ->setTo([$forgotUser['email'] => $forgotUser['username']])
                    ->setBody($messageBody);

                Application::mailer()->send($message);
            }

            header("Location: ?m=reset&u={$forgotUser['user_id']}");
            break;
        }

        echo tpl_render('auth.auth');
        break;

    case 'login':
        if ($app->hasActiveSession()) {
            header('Location: /');
            break;
        }

        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $authLoginError = '';

        while ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ipAddress = IPAddress::remote()->getString();

            if (!isset($_POST['username'], $_POST['password'])) {
                $authLoginError = "You didn't fill all the forms!";
                break;
            }

            $remainingAttempts = user_login_attempts_remaining($ipAddress);

            if ($remainingAttempts < 1) {
                $authLoginError = 'Too many failed login attempts, try again later.';
                break;
            }

            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            $getUser = Database::prepare('
                SELECT `user_id`, `password`
                FROM `msz_users`
                WHERE LOWER(`email`) = LOWER(:email)
                OR LOWER(`username`) = LOWER(:username)
            ');
            $getUser->bindValue('email', $username);
            $getUser->bindValue('username', $username);
            $userData = $getUser->execute() ? $getUser->fetch() : [];
            $userId = (int)($userData['user_id'] ?? 0);

            $loginFailedError = sprintf(
                "Invalid username or password, %d attempt%s remaining.",
                $remainingAttempts - 1,
                $remainingAttempts === 2 ? '' : 's'
            );

            if ($userId < 1) {
                user_login_attempt_record(false, null, $ipAddress, $userAgent);
                $authLoginError = $loginFailedError;
                break;
            }

            if (!password_verify($password, $userData['password'])) {
                user_login_attempt_record(false, $userId, $ipAddress, $userAgent);
                $authLoginError = $loginFailedError;
                break;
            }

            user_login_attempt_record(true, $userId, $ipAddress, $userAgent);
            $sessionKey = user_session_create($userId, $ipAddress, $userAgent);

            if ($sessionKey === '') {
                $authLoginError = 'Unable to create new session, contact an administrator ASAP.';
                break;
            }

            $app->startSession($userId, $sessionKey);
            $cookieLife = Carbon::now()->addMonth()->timestamp;
            set_cookie_m('uid', $userId, $cookieLife);
            set_cookie_m('sid', $sessionKey, $cookieLife);

            header('Location: /');
            return;
        }

        if (!empty($authLoginError)) {
            tpl_var('auth_login_error', $authLoginError);
        }

        echo tpl_render('auth.auth');
        break;

    case 'register':
        if ($app->hasActiveSession()) {
            header('Location: /');
        }

        $authRegistrationError = '';

        while ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($preventRegistration) {
                $authRegistrationError = 'Registration is not allowed on this instance.';
                break;
            }

            if (!isset($_POST['username'], $_POST['password'], $_POST['email'])) {
                $authRegistrationError = "You didn't fill all the forms!";
                break;
            }

            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $email = $_POST['email'] ?? '';

            $usernameValidation = user_validate_username($username, true);
            if ($usernameValidation !== '') {
                $authRegistrationError = $usernameValidationErrors[$usernameValidation];
                break;
            }

            $emailValidation = user_validate_email($email, true);
            if ($emailValidation !== '') {
                $authRegistrationError = $emailValidation === 'in-use'
                    ? 'This e-mail address has already been used!'
                    : 'The e-mail address you entered is invalid!';
                break;
            }

            if (user_validate_password($password) !== '') {
                $authRegistrationError = 'Your password is too weak!';
                break;
            }

            $createUser = user_create(
                $username,
                $password,
                $email,
                IPAddress::remote()->getString()
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
