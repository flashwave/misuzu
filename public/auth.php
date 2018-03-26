<?php
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Misuzu\Application;
use Misuzu\Database;
use Misuzu\Net\IPAddress;
use Misuzu\Users\User;
use Misuzu\Users\Session;
use Misuzu\Users\LoginAttempt;

require_once __DIR__ . '/../misuzu.php';

$username_validation_errors = [
    'trim' => 'Your username may not start or end with spaces!',
    'short' => "Your username is too short, it has to be at least " . User::USERNAME_MIN_LENGTH . " characters!",
    'long' => "Your username is too long, it can't be longer than " . User::USERNAME_MAX_LENGTH . " characters!",
    'double-spaces' => "Your username can't contain double spaces.",
    'invalid' => 'Your username contains invalid characters.',
    'spacing' => 'Please use either underscores or spaces, not both!',
    'in-use' => 'This username is already taken!',
];

$mode = $_GET['m'] ?? 'login';
$prevent_registration = $app->config->get('Auth', 'prevent_registration', 'bool', false);
$app->templating->var('auth_mode', $mode);
$app->templating->addPath('auth', __DIR__ . '/../views/auth');
$app->templating->var('prevent_registration', $prevent_registration);

if (!empty($_REQUEST['username'])) {
    $app->templating->var('auth_username', $_REQUEST['username']);
}

if (!empty($_REQUEST['email'])) {
    $app->templating->var('auth_email', $_REQUEST['email']);
}

switch ($mode) {
    case 'logout':
        if ($app->getSession() === null) {
            header('Location: /');
            return;
        }

        // this is temporary, don't scream at me for using md5
        if (isset($_GET['s']) && tmp_csrf_verify($_GET['s'])) {
            set_cookie_m('uid', '', -3600);
            set_cookie_m('sid', '', -3600);
            $app->getSession()->delete();
            $app->setSession(null);
            header('Location: /');
            return;
        }

        echo $app->templating->render('logout');
        break;

    case 'login':
        if ($app->getSession() !== null) {
            header('Location: /');
            break;
        }

        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $auth_login_error = '';

        while ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ipAddress = IPAddress::remote();

            if (!isset($_POST['username'], $_POST['password'])) {
                $auth_login_error = "You didn't fill all the forms!";
                break;
            }

            $loginAttempts = LoginAttempt::fromIpAddress(IPAddress::remote())
                ->where('was_successful', false)
                ->where('created_at', '>', Carbon::now()->subHour()->toDateTimeString())
                ->get();

            if ($loginAttempts->count() >= 5) {
                $auth_login_error = 'Too many failed login attempts, try again later.';
                break;
            }

            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            $user = User::findLogin($username);

            if ($user === null) {
                LoginAttempt::recordFail($ipAddress, null, $user_agent);
                $auth_login_error = 'Invalid username or password!';
                break;
            }

            if (!$user->verifyPassword($password)) {
                LoginAttempt::recordFail($ipAddress, $user, $user_agent);
                $auth_login_error = 'Invalid username or password!';
                break;
            }

            LoginAttempt::recordSuccess($ipAddress, $user, $user_agent);

            $session = Session::createSession($user, $user_agent, null, $ipAddress);
            $app->setSession($session);
            set_cookie_m('uid', $session->user_id, 604800);
            set_cookie_m('sid', $session->session_key, 604800);

            // Temporary key generation for chat login.
            // Should eventually be replaced with a callback login system.
            // Also uses different cookies since $httponly is required to be false for these.
            $user->last_ip = $ipAddress;
            $user->user_chat_key = bin2hex(random_bytes(16));
            $user->save();

            setcookie('msz_tmp_id', $user->user_id, time() + 604800, '/', '.flashii.net');
            setcookie('msz_tmp_key', $user->user_chat_key, time() + 604800, '/', '.flashii.net');
            header('Location: /');
            return;
        }

        if (!empty($auth_login_error)) {
            $app->templating->var('auth_login_error', $auth_login_error);
        }

        echo $app->templating->render('auth');
        break;

    case 'register':
        if ($app->getSession() !== null) {
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

            $username_validate = User::validateUsername($username, true);
            if ($username_validate !== '') {
                $auth_register_error = $username_validation_errors[$username_validate];
                break;
            }

            $email_validate = User::validateEmail($email, true);
            if ($email_validate !== '') {
                $auth_register_error = $email_validate === 'in-use'
                    ? 'This e-mail address has already been used!'
                    : 'The e-mail address you entered is invalid!';
                break;
            }

            if (User::validatePassword($password) !== '') {
                $auth_register_error = 'Your password is too weak!';
                break;
            }

            User::createUser($username, $password, $email);
            $app->templating->var('auth_register_message', 'Welcome to Flashii! You may now log in.');
            break;
        }

        if (!empty($auth_register_error)) {
            $app->templating->var('auth_register_error', $auth_register_error);
        }

        echo $app->templating->render('auth');
        break;
}
