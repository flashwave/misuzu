<?php
use Carbon\Carbon;
use Misuzu\Database;
use Misuzu\Net\IPAddress;
use Misuzu\Users\Session;

require_once __DIR__ . '/../misuzu.php';

$config = $app->getConfig();
$templating = $app->getTemplating();

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
$templating->addPath('auth', __DIR__ . '/../views/auth');

$templating->vars([
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

        echo $templating->render('@auth.logout');
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

            if (strpos($_SERVER['HTTP_HOST'], 'flashii.net') !== false) {
                $chatKey = user_generate_chat_key($userId);

                if ($chatKey !== '') {
                    setcookie('msz_tmp_id', $userId, $cookieLife, '/', '.flashii.net');
                    setcookie('msz_tmp_key', $chatKey, $cookieLife, '/', '.flashii.net');
                }
            }

            header('Location: /');
            return;
        }

        if (!empty($authLoginError)) {
            $templating->var('auth_login_error', $authLoginError);
        }

        echo $templating->render('auth');
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

            $templating->var('auth_register_message', 'Welcome to Flashii! You may now log in.');
            break;
        }

        if (!empty($authRegistrationError)) {
            $templating->var('auth_register_error', $authRegistrationError);
        }

        echo $templating->render('@auth.auth');
        break;
}
