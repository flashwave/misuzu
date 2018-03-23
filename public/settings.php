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
                if (isset($_POST['profile']['twitter'])) {
                    $user_twitter = '';

                    if (!empty($_POST['profile']['twitter'])) {
                        $twitter_regex = preg_match(
                            '#^(?:https?://(?:www\.)?twitter.com/(?:\#!\/)?)?@?([A-Za-z0-9_]{1,20})/?$#u',
                            $_POST['profile']['twitter'],
                            $twitter_matches
                        );

                        if ($twitter_regex !== 1) {
                            $settings_errors[] = "Invalid Twitter field.";
                            break;
                        }

                        $user_twitter = $twitter_matches[1];
                    }

                    $settings_user->user_twitter = $user_twitter;
                }

                if (isset($_POST['profile']['osu'])) {
                    $user_osu = '';

                    if (!empty($_POST['profile']['osu'])) {
                        $osu_regex = preg_match(
                            '#^(?:https?://osu.ppy.sh/u(?:sers)?/)?([a-zA-Z0-9-\[\]_ ]{1,20})/?$#u',
                            $_POST['profile']['osu'],
                            $osu_matches
                        );

                        if ($osu_regex !== 1) {
                            $settings_errors[] = "Invalid osu! field.";
                            break;
                        }

                        $user_osu = $osu_matches[1];
                    }

                    $settings_user->user_osu = $user_osu;
                }

                if (isset($_POST['profile']['website'])) {
                    $user_website = '';

                    if (!empty($_POST['profile']['website'])) {
                        $website_regex = preg_match(
                            '#^(?:https?)://(.{1,240})$#u',
                            $_POST['profile']['website'],
                            $website_matches
                        );

                        if ($website_regex !== 1) {
                            $settings_errors[] = "Invalid website field.";
                            break;
                        }

                        $user_website = $website_matches[0];
                    }

                    $settings_user->user_website = $user_website;
                }

                if (isset($_POST['profile']['youtube'])) {
                    $user_youtube = '';

                    if (!empty($_POST['profile']['youtube'])) {
                        $youtube_regex = preg_match(
                            '#^(?:https?://(?:www.)?youtube.com/(?:(?:user|c|channel)/)?)?'
                            . '(UC[a-zA-Z0-9-_]{1,22}|[a-zA-Z0-9-_%]{1,100})/?$#u',
                            $_POST['profile']['youtube'],
                            $youtube_matches
                        );

                        if ($youtube_regex !== 1) {
                            $settings_errors[] = "Invalid Youtube field.";
                            break;
                        }

                        $user_youtube = $youtube_matches[1];
                    }

                    $settings_user->user_youtube = $user_youtube;
                }

                if (isset($_POST['profile']['steam'])) {
                    $user_steam = '';

                    if (!empty($_POST['profile']['steam'])) {
                        $steam_regex = preg_match(
                            '#^(?:https?://(?:www.)?steamcommunity.com/(?:id|profiles)/)?([a-zA-Z0-9_-]{2,100})/?$#u',
                            $_POST['profile']['steam'],
                            $steam_matches
                        );

                        if ($steam_regex !== 1) {
                            $settings_errors[] = "Invalid Steam field.";
                            break;
                        }

                        $user_steam = $steam_matches[1];
                    }

                    $settings_user->user_steam = $user_steam;
                }

                if (isset($_POST['profile']['twitchtv'])) {
                    $user_twitchtv = '';

                    if (!empty($_POST['profile']['twitchtv'])) {
                        $twitchtv_regex = preg_match(
                            '#^(?:https?://(?:www.)?twitch.tv/)?([0-9A-Za-z_]{3,25})/?$#u',
                            $_POST['profile']['twitchtv'],
                            $twitchtv_matches
                        );

                        if ($twitchtv_regex !== 1) {
                            $settings_errors[] = "Invalid Twitch.TV field.";
                            break;
                        }

                        $user_twitchtv = $twitchtv_matches[1];
                    }

                    $settings_user->user_twitchtv = $user_twitchtv;
                }

                if (isset($_POST['profile']['lastfm'])) {
                    $user_lastfm = '';

                    if (!empty($_POST['profile']['lastfm'])) {
                        $lastfm_regex = preg_match(
                            '#^(?:https?://(?:www.)?last.fm/user/)?([a-zA-Z]{1}[a-zA-Z0-9_-]{1,14})/?$#u',
                            $_POST['profile']['lastfm'],
                            $lastfm_matches
                        );

                        if ($lastfm_regex !== 1) {
                            $settings_errors[] = "Invalid Last.fm field.";
                            break;
                        }

                        $user_lastfm = $lastfm_matches[1];
                    }

                    $settings_user->user_lastfm = $user_lastfm;
                }

                if (isset($_POST['profile']['github'])) {
                    $user_github = '';

                    if (!empty($_POST['profile']['github'])) {
                        $github_regex = preg_match(
                            '#^(?:https?://(?:www.)?github.com/?)?'
                            . '([a-zA-Z0-9](?:[a-zA-Z0-9]|-(?=[a-zA-Z0-9])){0,38})/?$#u',
                            $_POST['profile']['github'],
                            $github_matches
                        );

                        if ($github_regex !== 1) {
                            $settings_errors[] = "Invalid Github field.";
                            break;
                        }

                        $user_github = $github_matches[1];
                    }

                    $settings_user->user_github = $user_github;
                }

                if (isset($_POST['profile']['skype'])) {
                    $user_skype = '';

                    if (!empty($_POST['profile']['skype'])) {
                        $skype_regex = preg_match(
                            '#^((?:live:)?[a-zA-Z][\w\.,\-_@]{1,100})$#u',
                            $_POST['profile']['skype'],
                            $skype_matches
                        );

                        if ($skype_regex !== 1) {
                            $settings_errors[] = "Invalid Skype field.";
                            break;
                        }

                        $user_skype = $skype_matches[1];
                    }

                    $settings_user->user_skype = $user_skype;
                }

                if (isset($_POST['profile']['discord'])) {
                    $user_discord = '';

                    if (!empty($_POST['profile']['discord'])) {
                        $discord_regex = preg_match(
                            '#^(.{1,32}\#[0-9]{4})$#u',
                            $_POST['profile']['discord'],
                            $discord_matches
                        );

                        if ($discord_regex !== 1) {
                            $settings_errors[] = "Invalid Discord field.";
                            break;
                        }

                        $user_discord = $discord_matches[1];
                    }

                    $settings_user->user_discord = $user_discord;
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
    case 'sessions':
        $app->templating->var('user_sessions', $settings_user->sessions->reverse());
        break;

    case 'login-history':
        $app->templating->var('user_login_attempts', $settings_user->loginAttempts->reverse());
        break;
}

echo $app->templating->render("settings.{$settings_mode}");
