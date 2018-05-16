<?php
use Misuzu\Database;
use Misuzu\IO\File;

require_once __DIR__ . '/../misuzu.php';

$db = Database::connection();
$templating = $app->getTemplating();

$page_id = (int)($_GET['p'] ?? 1);

if (!$app->hasActiveSession()) {
    http_response_code(403);
    echo $templating->render('errors.403');
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

$settings_modes = [
    'account' => 'Account',
    'avatar' => 'Avatar',
    'sessions' => 'Sessions',
    'login-history' => 'Login History',
];
$settings_mode = $_GET['m'] ?? key($settings_modes);

$templating->vars(compact('settings_mode', 'settings_modes'));

if (!array_key_exists($settings_mode, $settings_modes)) {
    http_response_code(404);
    $templating->var('settings_title', 'Not Found');
    echo $templating->render('settings.notfound');
    return;
}

$settings_errors = [];

$prevent_registration = $app->getConfig()->get('Auth', 'prevent_registration', 'bool', false);
$avatar_filename = "{$app->getUserId()}.msz";
$avatar_max_width = $app->getConfig()->get('Avatar', 'max_width', 'int', 4000);
$avatar_max_height = $app->getConfig()->get('Avatar', 'max_height', 'int', 4000);
$avatar_max_filesize = $app->getConfig()->get('Avatar', 'max_filesize', 'int', 1000000);
$avatar_max_filesize_human = byte_symbol($avatar_max_filesize, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($settings_mode) {
        case 'account':
            if (!tmp_csrf_verify($_POST['csrf'] ?? '')) {
                $settings_errors[] = $csrf_error_str;
                break;
            }

            $updatedUserFields = [];

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

                        $updatedUserFields["user_{$name}"] = $field_value;
                    }
                }
            }

            if (!$prevent_registration) {
                if (!empty($_POST['current_password'])
                || (
                    (isset($_POST['password']) || isset($_OST['email']))
                    && (!empty($_POST['password']['new']) || !empty($_POST['email']['new']))
                )
                ) {
                    $fetchPassword = $db->prepare('
                        SELECT `password`
                        FROM `msz_users`
                        WHERE `user_id` = :user_id
                    ');
                    $fetchPassword->bindValue('user_id', $app->getUserId());
                    $currentPassword = $fetchPassword->execute() ? $fetchPassword->fetchColumn() : null;

                    if (empty($currentPassword)) {
                        $settings_errors[] = 'Something went horribly wrong.';
                        break;
                    }

                    if (!password_verify($_POST['current_password'], $currentPassword)) {
                        $settings_errors[] = 'Your current password was incorrect.';
                        break;
                    }

                    if (!empty($_POST['email']['new'])) {
                        if (empty($_POST['email']['confirm'])
                            || $_POST['email']['new'] !== $_POST['email']['confirm']) {
                            $settings_errors[] = 'The given e-mail addresses did not match.';
                            break;
                        }

                        $checkIfAlreadySet = $db->prepare('
                            SELECT COUNT(`user_id`)
                            FROM `msz_users`
                            WHERE LOWER(:email) = LOWER(:email)
                        ');
                        $checkIfAlreadySet->bindValue('email', $_POST['email']['new']);
                        $isAlreadySet = $checkIfAlreadySet->execute()
                            ? $checkIfAlreadySet->fetchColumn() > 0
                            : false;

                        if ($isAlreadySet) {
                            $settings_errors[] = 'This is your e-mail address already!';
                            break;
                        }

                        $email_validate = user_validate_email($_POST['email']['new'], true);

                        if ($email_validate !== '') {
                            switch ($email_validate) {
                                case 'dns':
                                    $settings_errors[] = 'No valid MX record exists for this domain.';
                                    break;

                                case 'format':
                                    $settings_errors[] = 'The given e-mail address was incorrectly formatted.';
                                    break;

                                case 'in-use':
                                    $settings_errors[] = 'This e-mail address has already been used by another user.';
                                    break;

                                default:
                                    $settings_errors[] = 'Unknown e-mail validation error.';
                            }
                            break;
                        }

                        $updatedUserFields['email'] = strtolower($_POST['email']['new']);
                    }

                    if (!empty($_POST['password']['new'])) {
                        if (empty($_POST['password']['confirm'])
                        || $_POST['password']['new'] !== $_POST['password']['confirm']) {
                            $settings_errors[] = "The given passwords did not match.";
                            break;
                        }

                        $password_validate = user_validate_password($_POST['password']['new']);

                        if ($password_validate !== '') {
                            $settings_errors[] = "The given passwords was too weak.";
                            break;
                        }

                        $updatedUserFields['password'] = password_hash($_POST['password']['new'], PASSWORD_ARGON2I);
                    }
                }
            }

            if (count($settings_errors) < 1 && count($updatedUserFields) > 0) {
                $updateUser = $db->prepare('
                    UPDATE `msz_users`
                    SET ' . pdo_prepare_array_update($updatedUserFields, true) . '
                    WHERE `user_id` = :user_id
                ');
                $updatedUserFields['user_id'] = $app->getUserId();
                $updateUser->execute($updatedUserFields);
            }
            break;

        case 'avatar':
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
                $settings_errors[] = 'Invalid session.';
                break;
            }

            $findSession = $db->prepare('
                SELECT `session_id`, `user_id`
                FROM `msz_sessions`
                WHERE `session_id` = :session_id
            ');
            $findSession->bindValue('session_id', $session_id);
            $session = $findSession->execute() ? $findSession->fetch() : null;

            if (!$session || (int)$session['user_id'] !== $app->getUserId()) {
                $settings_errors[] = 'You may only end your own sessions.';
                break;
            }

            if ((int)$session['session_id'] === $app->getSessionId()) {
                header('Location: /auth.php?m=logout&s=' . tmp_csrf_token());
                return;
            }

            $deleteSession = $db->prepare('
                DELETE FROM `msz_sessions`
                WHERE `session_id` = :session_id
            ');
            $deleteSession->bindValue('session_id', $session['session_id']);
            $deleteSession->execute();
            break;
    }
}

$templating->vars(compact('settings_errors'));
$templating->var('settings_title', $settings_modes[$settings_mode]);

switch ($settings_mode) {
    case 'account':
        $getUserFields = $db->prepare('
            SELECT ' . pdo_prepare_array($settings_profile_fields, true, '`user_%s`') . '
            FROM `msz_users`
            WHERE `user_id` = :user_id
        ');
        $getUserFields->bindValue('user_id', $app->getUserId());
        $userFields = $getUserFields->execute() ? $getUserFields->fetch() : [];

        $templating->var('settings_profile_values', $userFields);
        $templating->vars(compact('settings_profile_fields', 'prevent_registration'));
        break;

    case 'avatar':
        $user_has_avatar = File::exists($app->getStore('avatars/original')->filename($avatar_filename));
        $templating->var('avatar_user_id', $app->getUserId());
        $templating->vars(compact(
            'avatar_max_width',
            'avatar_max_height',
            'avatar_max_filesize',
            'user_has_avatar'
        ));
        break;

    case 'sessions':
        /*$sessions = $settings_user->sessions()
           ->orderBy('session_id', 'desc')
           ->paginate(15, ['*'], 'p', $page_id);*/

        $getSessions = $db->prepare('
            SELECT
                `session_id`, `session_country`, `user_agent`, `created_at`, `expires_on`,
                INET6_NTOA(`session_ip`) as `session_ip_decoded`
            FROM `msz_sessions`
            WHERE `user_id` = :user_id
            ORDER BY `session_id` DESC
            LIMIT 0, 15
        ');
        $getSessions->bindValue('user_id', $app->getUserId());
        $sessions = $getSessions->execute() ? $getSessions->fetchAll() : [];

        $templating->var('active_session_id', $app->getSessionId());
        $templating->var('user_sessions', $sessions);
        break;

    case 'login-history':
        /*$login_attempts = $settings_user->loginAttempts()
            ->orderBy('attempt_id', 'desc')
            ->paginate(15, ['*'], 'p', $page_id);*/

        $getLoginAttempts = $db->prepare('
            SELECT
                `attempt_id`, `attempt_country`, `was_successful`, `user_agent`, `created_at`,
                INET6_NTOA(`attempt_ip`) as `attempt_ip_decoded`
            FROM `msz_login_attempts`
            WHERE `user_id` = :user_id
            ORDER BY `attempt_id` DESC
            LIMIT 0, 15
        ');
        $getLoginAttempts->bindValue('user_id', $app->getUserId());
        $loginAttempts = $getLoginAttempts->execute() ? $getLoginAttempts->fetchAll() : [];

        $templating->var('user_login_attempts', $loginAttempts);
        break;
}

echo $templating->render("settings.{$settings_mode}");
