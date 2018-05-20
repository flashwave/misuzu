<?php
use Carbon\Carbon;
use Misuzu\Database;
use Misuzu\Net\IPAddress;
use Misuzu\Users\Session;

require_once __DIR__ . '/../misuzu.php';

$db = Database::connection();
$config = $app->getConfig();
$templating = $app->getTemplating();

$username_validation_errors = [
    'trim' => 'Your username may not start or end with spaces!',
    'short' => "Your username is too short, it has to be at least " . MSZ_USERNAME_MIN_LENGTH . " characters!",
    'long' => "Your username is too long, it can't be longer than " . MSZ_USERNAME_MAX_LENGTH . " characters!",
    'double-spaces' => "Your username can't contain double spaces.",
    'invalid' => 'Your username contains invalid characters.',
    'spacing' => 'Please use either underscores or spaces, not both!',
    'in-use' => 'This username is already taken!',
];

$mode = $_GET['m'] ?? 'login';
$prevent_registration = $config->get('Auth', 'prevent_registration', 'bool', false);
$templating->var('auth_mode', $mode);
$templating->addPath('auth', __DIR__ . '/../views/auth');
$templating->var('prevent_registration', $prevent_registration);

if (!empty($_REQUEST['username'])) {
    $templating->var('auth_username', $_REQUEST['username']);
}

if (!empty($_REQUEST['email'])) {
    $templating->var('auth_email', $_REQUEST['email']);
}

switch ($mode) {
    case 'logout':
        if (!$app->hasActiveSession()) {
            header('Location: /');
            return;
        }

        if (isset($_GET['s']) && tmp_csrf_verify($_GET['s'])) {
            set_cookie_m('uid', '', -3600);
            set_cookie_m('sid', '', -3600);
            $deleteSession = $db->prepare('
                DELETE FROM `msz_sessions`
                WHERE `session_id` = :session_id
            ');
            $deleteSession->bindValue('session_id', $app->getSessionId());
            $deleteSession->execute();
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

        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $auth_login_error = '';

        while ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ipAddress = IPAddress::remote()->getString();

            if (!isset($_POST['username'], $_POST['password'])) {
                $auth_login_error = "You didn't fill all the forms!";
                break;
            }

            $fetchRemainingAttempts = $db->prepare('
                SELECT 5 - COUNT(`attempt_id`)
                FROM `msz_login_attempts`
                WHERE `was_successful` = false
                AND `created_at` > NOW() - INTERVAL 1 HOUR
                AND `attempt_ip` = INET6_ATON(:remote_ip)
            ');
            $fetchRemainingAttempts->bindValue('remote_ip', $ipAddress);
            $remainingAttempts = $fetchRemainingAttempts->execute()
                ? (int)$fetchRemainingAttempts->fetchColumn()
                : 0;

            if ($remainingAttempts < 1) {
                $auth_login_error = 'Too many failed login attempts, try again later.';
                break;
            }

            $remainingAttempts -= 1;
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            $getUser = $db->prepare('
                SELECT `user_id`, `password`
                FROM `msz_users`
                WHERE LOWER(`email`) = LOWER(:email)
                OR LOWER(`username`) = LOWER(:username)
            ');
            $getUser->bindValue('email', $username);
            $getUser->bindValue('username', $username);
            $userData = $getUser->execute() ? $getUser->fetch() : [];
            $userId = (int)($userData['user_id'] ?? 0);

            $auth_error_str = "Invalid username or password, {$remainingAttempts} attempt(s) remaining.";

            if ($userId < 1) {
                user_login_attempt_record(false, null, $ipAddress, $user_agent);
                $auth_login_error = $auth_error_str;
                break;
            }

            if (!password_verify($password, $userData['password'])) {
                user_login_attempt_record(false, $userId, $ipAddress, $user_agent);
                $auth_login_error = $auth_error_str;
                break;
            }

            user_login_attempt_record(true, $userId, $ipAddress, $user_agent);

            $sessionKey = bin2hex(random_bytes(32));

            $createSession = $db->prepare('
                INSERT INTO `msz_sessions`
                    (
                        `user_id`, `session_ip`, `session_country`,
                        `user_agent`, `session_key`, `created_at`, `expires_on`
                    )
                VALUES
                    (
                        :user_id, INET6_ATON(:session_ip), :session_country,
                        :user_agent, :session_key, NOW(), NOW() + INTERVAL 1 MONTH
                    )
            ');
            $createSession->bindValue('user_id', $userId);
            $createSession->bindValue('session_ip', $ipAddress);
            $createSession->bindValue('session_country', get_country_code($ipAddress));
            $createSession->bindValue('user_agent', $user_agent);
            $createSession->bindValue('session_key', $sessionKey);

            if (!$createSession->execute()) {
                $auth_login_error = 'Unable to create new session, contact an administrator.';
                break;
            }

            $app->startSession($userId, $sessionKey);
            $cookieLife = Carbon::now()->addMonth()->timestamp;
            set_cookie_m('uid', $userId, $cookieLife);
            set_cookie_m('sid', $sessionKey, $cookieLife);

            // Temporary key generation for chat login.
            // Should eventually be replaced with a callback login system.
            // Also uses different cookies since $httponly is required to be false for these.
            $chatKey = bin2hex(random_bytes(16));

            $setChatKey = $db->prepare('
                UPDATE `msz_users`
                SET `user_chat_key` = :user_chat_key
                WHERE `user_id` = :user_id
            ');
            $setChatKey->bindValue('user_chat_key', $chatKey);
            $setChatKey->bindValue('user_id', $userId);

            if ($setChatKey->execute()) {
                setcookie('msz_tmp_id', $userId, $cookieLife, '/', '.flashii.net');
                setcookie('msz_tmp_key', $chatKey, $cookieLife, '/', '.flashii.net');
            }

            header('Location: /');
            return;
        }

        if (!empty($auth_login_error)) {
            $templating->var('auth_login_error', $auth_login_error);
        }

        echo $templating->render('auth');
        break;

    case 'register':
        if ($app->hasActiveSession()) {
            header('Location: /');
        }

        $auth_register_error = '';

        while ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($prevent_registration) {
                $auth_register_error = 'Registration is not allowed on this instance.';
                break;
            }

            if (!isset($_POST['username'], $_POST['password'], $_POST['email'])) {
                $auth_register_error = "You didn't fill all the forms!";
                break;
            }

            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $email = $_POST['email'] ?? '';

            $username_validate = user_validate_username($username, true);
            if ($username_validate !== '') {
                $auth_register_error = $username_validation_errors[$username_validate];
                break;
            }

            $email_validate = user_validate_email($email, true);
            if ($email_validate !== '') {
                $auth_register_error = $email_validate === 'in-use'
                    ? 'This e-mail address has already been used!'
                    : 'The e-mail address you entered is invalid!';
                break;
            }

            if (user_validate_password($password) !== '') {
                $auth_register_error = 'Your password is too weak!';
                break;
            }

            $ipAddress = IPAddress::remote()->getString();
            $createUser = $db->prepare('
                INSERT INTO `msz_users`
                    (
                        `username`, `password`, `email`, `register_ip`,
                        `last_ip`, `user_country`, `created_at`, `display_role`
                    )
                VALUES
                    (
                        :username, :password, :email, INET6_ATON(:register_ip),
                        INET6_ATON(:last_ip), :user_country, NOW(), 1
                    )
            ');
            $createUser->bindValue('username', $username);
            $createUser->bindValue('password', password_hash($password, PASSWORD_ARGON2I));
            $createUser->bindValue('email', $email);
            $createUser->bindValue('register_ip', $ipAddress);
            $createUser->bindValue('last_ip', $ipAddress);
            $createUser->bindValue('user_country', get_country_code($ipAddress));

            if (!$createUser->execute()) {
                $auth_register_error = 'Something happened?';
                break;
            }

            $addRole = $db->prepare('
                INSERT INTO `msz_user_roles`
                    (`user_id`, `role_id`)
                VALUES
                    (:user_id, 1)
            ');
            $addRole->bindValue('user_id', $db->lastInsertId());
            $addRole->execute();

            $templating->var('auth_register_message', 'Welcome to Flashii! You may now log in.');
            break;
        }

        if (!empty($auth_register_error)) {
            $templating->var('auth_register_error', $auth_register_error);
        }

        echo $templating->render('@auth.auth');
        break;
}
