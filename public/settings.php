<?php
use Misuzu\Application;
use Misuzu\IO\File;
use Misuzu\Users\User;
use Misuzu\Users\Session;

require_once __DIR__ . '/../misuzu.php';

$settings_session = Application::getInstance()->getSession();

if (Application::getInstance()->getSession() === null) {
    http_response_code(403);
    echo $app->templating->render('errors.403');
    return;
}

$csrf_error_str = "Couldn't verify you, please refresh the page and retry.";

$settings_profile_fields = [
    'twitter' => [
        'name' => 'Twitter',
        'regex' => '#^(?:https?://(?:www\.)?twitter.com/(?:\#!\/)?)?@?([A-Za-z0-9_]{1,20})/?$#u',
        'no-match' => 'Twitter field was invalid.',
    ],
    'osu' => [
        'name' => 'osu!',
        'regex' => '#^(?:https?://osu.ppy.sh/u(?:sers)?/)?([a-zA-Z0-9-\[\]_ ]{1,20})/?$#u',
        'no-match' => 'osu! field was invalid.',
    ],
    'website' => [
        'name' => 'Website',
        'type' => 'url',
        'regex' => '#^((?:https?)://.{1,240})$#u',
        'no-match' => 'Website field was invalid.',
    ],
    'youtube' => [
        'name' => 'Youtube',
        'regex' => '#^(?:https?://(?:www.)?youtube.com/(?:(?:user|c|channel)/)?)?(UC[a-zA-Z0-9-_]{1,22}|[a-zA-Z0-9-_%]{1,100})/?$#u',
        'no-match' => 'Youtube field was invalid.',
    ],
    'steam' => [
        'name' => 'Steam',
        'regex' => '#^(?:https?://(?:www.)?steamcommunity.com/(?:id|profiles)/)?([a-zA-Z0-9_-]{2,100})/?$#u',
        'no-match' => 'Steam field was invalid.',
    ],
    'twitchtv' => [
        'name' => 'Twitch.tv',
        'regex' => '#^(?:https?://(?:www.)?twitch.tv/)?([0-9A-Za-z_]{3,25})/?$#u',
        'no-match' => 'Twitch.tv field was invalid.',
    ],
    'lastfm' => [
        'name' => 'Last.fm',
        'regex' => '#^(?:https?://(?:www.)?last.fm/user/)?([a-zA-Z]{1}[a-zA-Z0-9_-]{1,14})/?$#u',
        'no-match' => 'Last.fm field was invalid.',
    ],
    'github' => [
        'name' => 'Github',
        'regex' => '#^(?:https?://(?:www.)?github.com/?)?([a-zA-Z0-9](?:[a-zA-Z0-9]|-(?=[a-zA-Z0-9])){0,38})/?$#u',
        'no-match' => 'Github field was invalid.',
    ],
    'skype' => [
        'name' => 'Skype',
        'regex' => '#^((?:live:)?[a-zA-Z][\w\.,\-_@]{1,100})$#u',
        'no-match' => 'Skype field was invalid.',
    ],
    'discord' => [
        'name' => 'Discord',
        'regex' => '#^(.{1,32}\#[0-9]{4})$#u',
        'no-match' => 'Discord field was invalid.',
    ],
];

$settings_user = $settings_session->user;

$settings_modes = [
    'account' => 'Account',
    'avatar' => 'Avatar',
    'sessions' => 'Sessions',
    'login-history' => 'Login History',
];
$settings_mode = $_GET['m'] ?? key($settings_modes);

$app->templating->vars(compact('settings_mode', 'settings_modes', 'settings_user', 'settings_session'));

if (!array_key_exists($settings_mode, $settings_modes)) {
    http_response_code(404);
    $app->templating->var('settings_title', 'Not Found');
    echo $app->templating->render('settings.notfound');
    return;
}

$settings_errors = [];

$avatar_filename = "{$settings_user->user_id}.msz";
$avatar_max_width = $app->config->get('Avatar', 'max_width', 'int', 4000);
$avatar_max_height = $app->config->get('Avatar', 'max_height', 'int', 4000);
$avatar_max_filesize = $app->config->get('Avatar', 'max_filesize', 'int', 1000000);
$avatar_max_filesize_human = byte_symbol($avatar_max_filesize, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($settings_mode) {
        case 'account':
            if (!tmp_csrf_verify($_POST['csrf'] ?? '')) {
                $settings_errors[] = $csrf_error_str;
                break;
            }

            if (isset($_POST['profile']) && is_array($_POST['profile'])) {
                foreach ($settings_profile_fields as $name => $props) {
                    if (isset($_POST['profile'][$name])) {
                        $field_value = '';

                        if (!empty($_POST['profile'][$name])) {
                            $field_regex = preg_match(
                                $props['regex'],
                                $_POST['profile'][$name],
                                $field_matches
                            );

                            if ($field_regex !== 1) {
                                $settings_errors[] = $props['no-match'];
                                break;
                            }

                            $field_value = $field_matches[1];
                        }

                        $settings_user->{"user_{$name}"} = $field_value;
                    }
                }
            }

            if (!empty($_POST['current_password'])
                || (
                    (isset($_POST['password']) || isset($_OST['email']))
                    && (!empty($_POST['password']['new']) || !empty($_POST['email']['new']))
                )
            ) {
                if (!$settings_user->verifyPassword($_POST['current_password'])) {
                    $settings_errors[] = "Your current password was incorrect.";
                    break;
                }

                if (!empty($_POST['email']['new'])) {
                    if (empty($_POST['email']['confirm']) || $_POST['email']['new'] !== $_POST['email']['confirm']) {
                        $settings_errors[] = "The given e-mail addresses did not match.";
                        break;
                    }

                    if ($_POST['email']['new'] === $settings_user->email) {
                        $settings_errors[] = "This is your e-mail address already!";
                        break;
                    }

                    $email_validate = User::validateEmail($_POST['email']['new'], true);

                    if ($email_validate !== '') {
                        switch ($email_validate) {
                            case 'dns':
                                $settings_errors[] = "No valid MX record exists for this domain.";
                                break;

                            case 'format':
                                $settings_errors[] = "The given e-mail address was incorrectly formatted.";
                                break;

                            case 'in-use':
                                $settings_errors[] = "This e-mail address has already been used by another user.";
                                break;

                            default:
                                $settings_errors[] = "Unknown e-mail validation error.";
                        }
                        break;
                    }

                    $settings_user->email = $_POST['email']['new'];
                }

                if (!empty($_POST['password']['new'])) {
                    if (empty($_POST['password']['confirm'])
                        || $_POST['password']['new'] !== $_POST['password']['confirm']) {
                        $settings_errors[] = "The given passwords did not match.";
                        break;
                    }

                    $password_validate = User::validatePassword($_POST['password']['new'], true);

                    if ($password_validate !== '') {
                        $settings_errors[] = "The given passwords was too weak.";
                        break;
                    }

                    $settings_user->password = $_POST['password']['new'];
                }
            }

            if (count($settings_errors) < 1 && $settings_user->isDirty()) {
                $settings_user->save();
            }
            break;

        case 'avatar':
            if (isset($_POST['import'])
                && !File::exists($app->getStore('avatars/original')->filename($avatar_filename))) {
                if (!tmp_csrf_verify($_POST['import'])) {
                    $settings_errors[] = $csrf_error_str;
                    break;
                }

                $old_avatar_url = trim(file_get_contents(
                    "https://secret.flashii.net/avatar-serve.php?id={$settings_user->user_id}&r"
                ));

                if (empty($old_avatar_url)) {
                    $settings_errors[] = 'No old avatar was found for you.';
                    break;
                }

                File::writeAll(
                    $app->getStore('avatars/original')->filename($avatar_filename),
                    file_get_contents($old_avatar_url)
                );
                break;
            }

            if (isset($_POST['delete'])) {
                if (!tmp_csrf_verify($_POST['delete'])) {
                    $settings_errors[] = $csrf_error_str;
                    break;
                }

                $delete_this = [
                    $app->getStore('avatars/original')->filename($avatar_filename),
                    $app->getStore('avatars/200x200')->filename($avatar_filename),
                ];

                foreach ($delete_this as $delete_avatar) {
                    if (File::exists($delete_avatar)) {
                        File::delete($delete_avatar);
                    }
                }
                break;
            }

            if (isset($_POST['upload'])) {
                if (!tmp_csrf_verify($_POST['upload'])) {
                    $settings_errors[] = $csrf_error_str;
                    break;
                }

                switch ($_FILES['avatar']['error']) {
                    case UPLOAD_ERR_OK:
                        break;

                    case UPLOAD_ERR_NO_FILE:
                        $settings_errors[] = 'Select a file before hitting upload!';
                        break;

                    case UPLOAD_ERR_PARTIAL:
                        $settings_errors[] = 'The upload was interrupted, please try again!';
                        break;

                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $settings_errors[] = "Your avatar is not allowed to be larger in filesize than {$avatar_max_filesize_human}!";
                        break;

                    case UPLOAD_ERR_NO_TMP_DIR:
                    case UPLOAD_ERR_CANT_WRITE:
                        $settings_errors[] = 'Unable to save your avatar, contact an administator!';
                        break;

                    case UPLOAD_ERR_EXTENSION:
                    default:
                        $settings_errors[] = 'Something happened?';
                        break;
                }

                if (count($settings_errors) > 0) {
                    break;
                }

                $upload_path = $_FILES['avatar']['tmp_name'];
                $upload_meta = getimagesize($upload_path);

                if (!$upload_meta
                    || !in_array($upload_meta[2], [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG], true)
                    || $upload_meta[0] < 1
                    || $upload_meta[1] < 1) {
                    $settings_errors[] = 'Please provide a valid image.';
                    break;
                }

                if ($upload_meta[0] > $avatar_max_width || $upload_meta[1] > $avatar_max_height) {
                    $settings_errors[] = "Your avatar can't be larger than {$avatar_max_width}x{$avatar_max_height}, yours was {$upload_meta[0]}x{$upload_meta[1]}";
                    break;
                }

                if (filesize($upload_path) > $avatar_max_filesize) {
                    $settings_errors[] = "Your avatar is not allowed to be larger in filesize than {$avatar_max_filesize_human}!";
                    break;
                }

                $avatar_path = $app->getStore('avatars/original')->filename($avatar_filename);
                move_uploaded_file($upload_path, $avatar_path);

                $crop_path = $app->getStore('avatars/200x200')->filename($avatar_filename);

                if (File::exists($crop_path)) {
                    File::delete($crop_path);
                }
                break;
            }

            $settings_errors[] = "You shouldn't have done that.";
            break;

        case 'sessions':
            if (!tmp_csrf_verify($_POST['csrf'] ?? '')) {
                $settings_errors[] = $csrf_error_str;
                break;
            }

            $session_id = (int)($_POST['session'] ?? 0);

            if ($session_id < 1) {
                $settings_errors[] = 'no';
                break;
            }

            $session = Session::find($session_id);

            if ($session === null || $session->user_id !== $settings_user->user_id) {
                $settings_errors[] = 'You may only end your own sessions.';
                break;
            }

            if ($session->session_id === $app->getSession()->session_id) {
                header('Location: /auth.php?m=logout&s=' . tmp_csrf_token());
                return;
            }

            $session->delete();
            break;
    }
}

$app->templating->vars(compact('settings_errors'));
$app->templating->var('settings_title', $settings_modes[$settings_mode]);

switch ($settings_mode) {
    case 'account':
        $app->templating->vars(compact('settings_profile_fields'));
        break;

    case 'avatar':
        $user_has_avatar = File::exists($app->getStore('avatars/original')->filename($avatar_filename));
        $app->templating->vars(compact(
            'avatar_max_width',
            'avatar_max_height',
            'avatar_max_filesize',
            'user_has_avatar'
        ));
        break;

    case 'sessions':
        $app->templating->var('user_sessions', $settings_user->sessions->reverse());
        break;

    case 'login-history':
        $app->templating->var('user_login_attempts', $settings_user->loginAttempts->reverse());
        break;
}

echo $app->templating->render("settings.{$settings_mode}");
