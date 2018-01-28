<?php
namespace Misuzu\Controllers;

use Aitemu\RouterResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Misuzu\Application;
use Misuzu\Database;
use Misuzu\Net\IP;
use Misuzu\Users\User;

class AuthController extends Controller
{
    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $app = Application::getInstance();
            $twig = $app->templating;

            return $twig->render('auth.login');
        }

        if (!isset($_POST['username'], $_POST['password'])) {
            return ['error' => "You didn't fill all the forms!"];
        }

        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        try {
            $user = User::where('username', $username)->firstOrFail();
        } catch (ModelNotFoundException $e) {
            return ['error' => 'Invalid username or password!'];
        }

        if (!password_verify($password, $user->password)) {
            return ['error' => 'Invalid username or password!'];
        }

        $_SESSION['user_id'] = $user->user_id;
        $_SESSION['username'] = $user->username;

        $user->user_chat_key = $_SESSION['chat_key'] = bin2hex(random_bytes(16));
        $user->save();

        setcookie('msz_tmp_id', $_SESSION['user_id'], time() + 604800, '/', '.flashii.net');
        setcookie('msz_tmp_key', $_SESSION['chat_key'], time() + 604800, '/', '.flashii.net');

        return ['error' => 'You are now logged in!', 'next' => '/'];
    }

    private function hasRegistrations(?string $ipAddr = null): bool
    {
        $ipAddr = IP::unpack($ipAddr ?? IP::remote());

        if (User::where('register_ip', $ipAddr)->orWhere('last_ip', $ipAddr)->count()) {
            return true;
        }

        return false;
    }

    public function register()
    {
        $app = Application::getInstance();
        $allowed_to_reg = $app->config->get('Testing', 'public_registration', 'bool', false)
            || in_array(
                IP::remote(),
                explode(' ', $app->config->get('Testing', 'allow_ip_registration', 'string', '127.0.0.1 ::1'))
            );

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $twig = $app->templating;
            $twig->vars([
                'has_registrations' => $this->hasRegistrations(),
                'allowed_to_register' => $allowed_to_reg,
            ]);

            return $twig->render('auth.register');
        }

        if (!$allowed_to_reg) {
            return [
                'error' => "Nice try, but you'll have to wait a little longer. I appreciate your excitement though!"
            ];
        }

        if ($this->hasRegistrations()) {
            return [
                'error' => "Someone already used an account from this IP address!\r\n"
                    . "But don't worry, this is a temporary measure and you'll be able to register sometime soon."
            ];
        }

        if (!isset($_POST['username'], $_POST['password'], $_POST['email'])) {
            return ['error' => "You didn't fill all the forms!"];
        }

        $username = $_POST['username'] ?? '';
        $username_validate = $this->validateUsername($username);
        $password = $_POST['password'] ?? '';
        $email = $_POST['email'] ?? '';

        if ($username_validate !== '') {
            return ['error' => $username_validate];
        }

        try {
            $existing = User::where('username', $username)->firstOrFail();

            if ($existing->user_id > 0) {
                return ['error' => 'This username is already taken!'];
            }
        } catch (ModelNotFoundException $e) {
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !check_mx_record($email)) {
            return ['error' => 'The e-mail address you entered is invalid!'];
        }

        try {
            $existing = User::where('email', $email)->firstOrFail();

            if ($existing->user_id > 0) {
                return ['error' => 'This e-mail address has already been used!'];
            }
        } catch (ModelNotFoundException $e) {
        }

        if (password_entropy($password) < 32) {
            return ['error' => 'Your password is considered too weak!'];
        }

        $user = new User;
        $user->username = $username;
        $user->password = password_hash($password, PASSWORD_ARGON2I);
        $user->email = $email;
        $user->register_ip = IP::unpack(IP::remote());
        $user->last_ip = IP::unpack(IP::remote());
        $user->user_country = get_country_code(IP::remote());
        $user->user_registered = time();
        $user->save();

        return ['error' => 'Welcome to Flashii! You may now log in.', 'next' => '/auth/login'];
    }

    public function logout()
    {
        session_destroy();
        return 'Logged out.<meta http-equiv="refresh" content="0; url=/">';
    }

    private function validateUsername(string $username): string
    {
        $username_length = strlen($username);

        if (($username ?? '') !== trim($username)) {
            return 'Your username may not start or end with spaces!';
        }

        if ($username_length < 3) {
            return "Your username is too short, it has to be at least 3 characters!";
        }

        if ($username_length > 16) {
            return "Your username is too long, it can't be longer than 16 characters!";
        }

        if (strpos($username, '  ') !== false || !preg_match('#^[A-Za-z0-9-\[\]_ ]+$#u', $username)) {
            return 'Your username contains invalid characters.';
        }

        if (strpos($username, '_') !== false && strpos($username, ' ') !== false) {
            return 'Please use either underscores or spaces, not both!';
        }

        return '';
    }
}
