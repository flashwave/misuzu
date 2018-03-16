<?php
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Misuzu\Application;
use Misuzu\Database;
use Misuzu\Net\IPAddress;
use Misuzu\Users\User;
use Misuzu\Users\Session;

require_once __DIR__ . '/../misuzu.php';

$username_validation_errors = [
    'trim' => 'Your username may not start or end with spaces!',
    'short' => "Your username is too short, it has to be at least " . User::USERNAME_MIN_LENGTH . " characters!",
    'long' => "Your username is too long, it can't be longer than " . User::USERNAME_MAX_LENGTH . " characters!",
    'double-spaces' => "Your username can't contain double spaces.",
    'invalid' => 'Your username contains invalid characters.',
    'spacing' => 'Please use either underscores or spaces, not both!',
];

$mode = $_GET['m'] ?? 'login';
$app->templating->var('auth_mode', $mode);

switch ($mode) {
    case 'logout':
        if ($app->getSession() === null) {
            echo "You aren't logged in.";
        } else {
            echo "You've been logged out.";
            set_cookie_m('uid', '', -3600);
            set_cookie_m('sid', '', -3600);
            $app->getSession()->delete();
            $app->setSession(null);
        }

        echo '<meta http-equiv="refresh" content="1; url=/">';
        break;

    case 'login':
        if ($app->getSession() !== null) {
            echo '<meta http-equiv="refresh" content="0; url=/">';
            break;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            echo $app->templating->render('auth.login');
            break;
        }

        if (!isset($_POST['username'], $_POST['password'])) {
            echo json_encode_m(['error' => "You didn't fill all the forms!"]);
            break;
        }

        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        try {
            $user = User::where('username', $username)->orWhere('email', $username)->firstOrFail();
        } catch (ModelNotFoundException $e) {
            echo json_encode_m(['error' => 'Invalid username or password!']);
            break;
        }

        if (!$user->validatePassword($password)) {
            echo json_encode_m(['error' => 'Invalid username or password!']);
            break;
        }

        $session = Session::createSession($user, 'Misuzu T2');
        $app->setSession($session);
        set_cookie_m('uid', $session->user_id, 604800);
        set_cookie_m('sid', $session->session_key, 604800);

        // Temporary key generation for chat login.
        // Should eventually be replaced with a callback login system.
        // Also uses different cookies since $httponly is required to be false for these.
        $user->last_ip = IPAddress::remote();
        $user->user_chat_key = bin2hex(random_bytes(16));
        $user->save();

        setcookie('msz_tmp_id', $user->user_id, time() + 604800, '/', '.flashii.net');
        setcookie('msz_tmp_key', $user->user_chat_key, time() + 604800, '/', '.flashii.net');

        echo json_encode_m(['error' => 'You are now logged in!', 'next' => '/']);
        break;

    case 'register':
        if ($app->getSession() !== null) {
            return '<meta http-equiv="refresh" content="0; url=/">';
        }

        $prevent_registration = $app->config->get('Auth', 'prevent_registration', 'bool', false);

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $app->templating->var('prevent_registration', $prevent_registration);
            echo $app->templating->render('auth.register');
            break;
        }

        if ($prevent_registration) {
            echo json_encode_m(['error' => 'Registration is not allowed on this instance.']);
            break;
        }

        if (!isset($_POST['username'], $_POST['password'], $_POST['email'])) {
            echo json_encode_m(['error' => "You didn't fill all the forms!"]);
            break;
        }

        $username = $_POST['username'] ?? '';
        $username_validate = User::validateUsername($username);
        $password = $_POST['password'] ?? '';
        $email = $_POST['email'] ?? '';

        if ($username_validate !== '') {
            echo json_encode_m(['error' => $username_validation_errors[$username_validate]]);
            break;
        }

        try {
            $existing = User::where('username', $username)->firstOrFail();

            if ($existing->user_id > 0) {
                echo json_encode_m(['error' => 'This username is already taken!']);
                break;
            }
        } catch (ModelNotFoundException $e) {
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !check_mx_record($email)) {
            echo json_encode_m(['error' => 'The e-mail address you entered is invalid!']);
            break;
        }

        try {
            $existing = User::where('email', $email)->firstOrFail();

            if ($existing->user_id > 0) {
                echo json_encode_m(['error' => 'This e-mail address has already been used!']);
                break;
            }
        } catch (ModelNotFoundException $e) {
        }

        if (password_entropy($password) < 32) {
            echo json_encode_m(['error' => 'Your password is too weak!']);
            break;
        }

        User::createUser($username, $password, $email);

        echo json_encode_m(['error' => 'Welcome to Flashii! You may now log in.', 'next' => '/auth.php?m=login']);
        break;
}
