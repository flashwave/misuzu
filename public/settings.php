<?php
use Misuzu\Application;
use Misuzu\Users\User;
use Misuzu\Users\Session;

require_once __DIR__ . '/../misuzu.php';

$settings_session = Application::getInstance()->getSession();

if ($settings_session === null) {
    header('Location: /');
    return;
}

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

$settings_mode = $_GET['m'] ?? null;
$settings_modes = [
    'account' => 'Account',
    'avatar' => 'Avatar',
    'sessions' => 'Sessions',
    'login-history' => 'Login History',
];

// if no mode is explicitly set just go to the index
if ($settings_mode === null) {
    $settings_mode = key($settings_modes);
}

$app->templating->vars(compact('settings_mode', 'settings_modes', 'settings_user'));

if (!array_key_exists($settings_mode, $settings_modes)) {
    $app->templating->var('settings_title', 'Not Found');
    echo $app->templating->render("settings.notfound");
    return;
}

$settings_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($settings_mode) {
        case 'account':
            if (!tmp_csrf_verify($_POST['csrf'] ?? '')) {
                $settings_errors[] = "Couldn't verify you, please refresh the page and retry.";
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
    }
}

$app->templating->vars(compact('settings_errors'));
$app->templating->var('settings_title', $settings_modes[$settings_mode]);

switch ($settings_mode) {
    case 'account':
        $app->templating->vars(compact('settings_profile_fields'));
        break;

    case 'sessions':
        $app->templating->var('user_sessions', $settings_user->sessions->reverse());
        break;

    case 'login-history':
        $app->templating->var('user_login_attempts', $settings_user->loginAttempts->reverse());
        break;
}

echo $app->templating->render("settings.{$settings_mode}");
