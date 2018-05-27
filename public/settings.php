<?php
use Misuzu\Database;
use Misuzu\IO\File;

require_once __DIR__ . '/../misuzu.php';

$db = Database::connection();
$templating = $app->getTemplating();

$queryOffset = (int)($_GET['o'] ?? 0);
$queryTake = 15;

if (!$app->hasActiveSession()) {
    echo render_error(403);
    return;
}

$csrfErrorString = "Couldn't verify you, please refresh the page and retry.";

$avatarErrorStrings = [
    'upload' => [
        'default' => 'Something happened? (UP:%1$d)',
        UPLOAD_ERR_OK => '',
        UPLOAD_ERR_NO_FILE => 'Select a file before hitting upload!',
        UPLOAD_ERR_PARTIAL => 'The upload was interrupted, please try again!',
        UPLOAD_ERR_INI_SIZE => 'Your avatar is not allowed to be larger in file size than %2$s!',
        UPLOAD_ERR_FORM_SIZE => 'Your avatar is not allowed to be larger in file size than %2$s!',
        UPLOAD_ERR_NO_TMP_DIR => 'Unable to save your avatar, contact an administator!',
        UPLOAD_ERR_CANT_WRITE => 'Unable to save your avatar, contact an administator!',
    ],
    'set' => [
        'default' => 'Something happened? (SET:%1$d)',
        MSZ_USER_AVATAR_NO_ERRORS => '',
        MSZ_USER_AVATAR_ERROR_INVALID_IMAGE => 'The file you uploaded was not an image!',
        MSZ_USER_AVATAR_ERROR_PROHIBITED_TYPE => 'This type of image is not supported, keep to PNG, JPG or GIF!',
        MSZ_USER_AVATAR_ERROR_DIMENSIONS_TOO_LARGE => 'Your avatar can\'t be larger than %3$dx%4$d!',
        MSZ_USER_AVATAR_ERROR_DATA_TOO_LARGE => 'Your avatar is not allowed to be larger in file size than %2$s!',
        MSZ_USER_AVATAR_ERROR_TMP_FAILED => 'Unable to save your avatar, contact an administator!',
        MSZ_USER_AVATAR_ERROR_STORE_FAILED => 'Unable to save your avatar, contact an administator!',
        MSZ_USER_AVATAR_ERROR_FILE_NOT_FOUND => 'Unable to save your avatar, contact an administator!',
    ],
];

$settingsModes = [
    'account' => 'Account',
    'avatar' => 'Avatar',
    'sessions' => 'Sessions',
    'login-history' => 'Login History',
];
$settingsMode = $_GET['m'] ?? key($settingsModes);

$templating->vars([
    'settings_mode' => $settingsMode,
    'settings_modes' => $settingsModes,
]);

if (!array_key_exists($settingsMode, $settingsModes)) {
    http_response_code(404);
    $templating->var('settings_title', 'Not Found');
    echo $templating->render('settings.notfound');
    return;
}

$settingsErrors = [];

$disableAccountOptions = $app->getConfig()->get('Auth', 'prevent_registration', 'bool', false);
$avatarFileName = "{$app->getUserId()}.msz";
$avatarWidthMax = $app->getConfig()->get('Avatar', 'max_width', 'int', 4000);
$avatarHeightMax = $app->getConfig()->get('Avatar', 'max_height', 'int', 4000);
$avatarFileSizeMax = $app->getConfig()->get('Avatar', 'max_filesize', 'int', 1000000);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($settingsMode) {
        case 'account':
            if (!tmp_csrf_verify($_POST['csrf'] ?? '')) {
                $settingsErrors[] = $csrfErrorString;
                break;
            }

            if (isset($_POST['profile']) && is_array($_POST['profile'])) {
                $setUserFieldErrors = user_profile_fields_set($app->getUserId(), $_POST['profile']);

                if (count($setUserFieldErrors) > 0) {
                    foreach ($setUserFieldErrors as $name => $error) {
                        switch ($error) {
                            case MSZ_USER_PROFILE_INVALID_FIELD:
                                $settingsErrors[] = sprintf("Field '%s' does not exist!", $name);
                                break;

                            case MSZ_USER_PROFILE_FILTER_FAILED:
                                $settingsErrors[] = sprintf(
                                    '%s field was invalid!',
                                    user_profile_field_get_display_name($name)
                                );
                                break;

                            case MSZ_USER_PROFILE_UPDATE_FAILED:
                                $settingsErrors[] = 'Failed to update values, contact an administator.';
                                break;

                            default:
                                $settingsErrors[] = 'An unexpected error occurred, contact an administator.';
                                break;
                        }
                    }
                }
            }

            if (!$disableAccountOptions) {
                if (!empty($_POST['current_password'])
                || (
                    (isset($_POST['password']) || isset($_POST['email']))
                    && (!empty($_POST['password']['new']) || !empty($_POST['email']['new']))
                )
                ) {
                    $updateAccountFields = [];

                    $fetchPassword = $db->prepare('
                        SELECT `password`
                        FROM `msz_users`
                        WHERE `user_id` = :user_id
                    ');
                    $fetchPassword->bindValue('user_id', $app->getUserId());
                    $currentPassword = $fetchPassword->execute() ? $fetchPassword->fetchColumn() : null;

                    if (empty($currentPassword)) {
                        $settingsErrors[] = 'Something went horribly wrong.';
                        break;
                    }

                    if (!password_verify($_POST['current_password'], $currentPassword)) {
                        $settingsErrors[] = 'Your current password was incorrect.';
                        break;
                    }

                    if (!empty($_POST['email']['new'])) {
                        if (empty($_POST['email']['confirm'])
                            || $_POST['email']['new'] !== $_POST['email']['confirm']) {
                            $settingsErrors[] = 'The given e-mail addresses did not match.';
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
                            $settingsErrors[] = 'This is your e-mail address already!';
                            break;
                        }

                        $email_validate = user_validate_email($_POST['email']['new'], true);

                        if ($email_validate !== '') {
                            switch ($email_validate) {
                                case 'dns':
                                    $settingsErrors[] = 'No valid MX record exists for this domain.';
                                    break;

                                case 'format':
                                    $settingsErrors[] = 'The given e-mail address was incorrectly formatted.';
                                    break;

                                case 'in-use':
                                    $settingsErrors[] = 'This e-mail address has already been used by another user.';
                                    break;

                                default:
                                    $settingsErrors[] = 'Unknown e-mail validation error.';
                            }
                            break;
                        }

                        $updateAccountFields['email'] = strtolower($_POST['email']['new']);
                    }

                    if (!empty($_POST['password']['new'])) {
                        if (empty($_POST['password']['confirm'])
                        || $_POST['password']['new'] !== $_POST['password']['confirm']) {
                            $settingsErrors[] = "The given passwords did not match.";
                            break;
                        }

                        $password_validate = user_validate_password($_POST['password']['new']);

                        if ($password_validate !== '') {
                            $settingsErrors[] = "The given passwords was too weak.";
                            break;
                        }

                        $updateAccountFields['password'] = user_password_hash($_POST['password']['new']);
                    }

                    if (count($updateAccountFields) > 0) {
                        $updateUser = $db->prepare('
                            UPDATE `msz_users`
                            SET ' . pdo_prepare_array_update($updateAccountFields, true) . '
                            WHERE `user_id` = :user_id
                        ');
                        $updateAccountFields['user_id'] = $app->getUserId();
                        $updateUser->execute($updateAccountFields);
                    }
                }
            }
            break;

        case 'avatar':
            if (isset($_POST['delete'])) {
                if (!tmp_csrf_verify($_POST['delete'])) {
                    $settingsErrors[] = $csrfErrorString;
                    break;
                }

                user_avatar_delete($app->getUserId());
                break;
            }

            if (isset($_POST['upload'])) {
                if (!tmp_csrf_verify($_POST['upload'])) {
                    $settingsErrors[] = $csrfErrorString;
                    break;
                }

                if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                    $settingsErrors[] = sprintf(
                        $avatarErrorStrings['upload'][$_FILES['avatar']['error']]
                            ?? $avatarErrorStrings['upload']['default'],
                        $_FILES['avatar']['error'],
                        byte_symbol($avatarFileSizeMax, true),
                        $avatarWidthMax,
                        $avatarHeightMax
                    );
                    break;
                }

                $setAvatar = user_avatar_set_from_path($app->getUserId(), $_FILES['avatar']['tmp_name']);

                if ($setAvatar !== MSZ_USER_AVATAR_NO_ERRORS) {
                    $settingsErrors[] = sprintf(
                        $avatarErrorStrings['set'][$setAvatar]
                            ?? $avatarErrorStrings['set']['default'],
                        $setAvatar,
                        byte_symbol($avatarFileSizeMax, true),
                        $avatarWidthMax,
                        $avatarHeightMax
                    );
                }
                break;
            }

            $settingsErrors[] = "You shouldn't have done that.";
            break;

        case 'sessions':
            if (!tmp_csrf_verify($_POST['csrf'] ?? '')) {
                $settingsErrors[] = $csrfErrorString;
                break;
            }

            $session_id = (int)($_POST['session'] ?? 0);

            if ($session_id < 1) {
                $settingsErrors[] = 'Invalid session.';
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
                $settingsErrors[] = 'You may only end your own sessions.';
                break;
            }

            if ((int)$session['session_id'] === $app->getSessionId()) {
                header('Location: /auth.php?m=logout&s=' . tmp_csrf_token());
                return;
            }

            user_session_delete($session['session_id']);
            break;
    }
}

$templating->var('settings_title', $settingsModes[$settingsMode]);
$templating->var('settings_errors', $settingsErrors);

switch ($settingsMode) {
    case 'account':
        $profileFields = user_profile_fields_get();
        $getUserFields = $db->prepare('
            SELECT ' . pdo_prepare_array($profileFields, true, '`user_%s`') . '
            FROM `msz_users`
            WHERE `user_id` = :user_id
        ');
        $getUserFields->bindValue('user_id', $app->getUserId());
        $userFields = $getUserFields->execute() ? $getUserFields->fetch() : [];

        $templating->vars([
            'settings_profile_fields' => $profileFields,
            'settings_profile_values' => $userFields,
            'settings_disable_account_options' => $disableAccountOptions,
        ]);
        break;

    case 'avatar':
        $userHasAvatar = File::exists($app->getStore('avatars/original')->filename($avatarFileName));
        $templating->vars([
            'avatar_user_id' => $app->getUserId(),
            'avatar_max_width' => $avatarWidthMax,
            'avatar_max_height' => $avatarHeightMax,
            'avatar_max_filesize' => $avatarFileSizeMax,
            'user_has_avatar' => $userHasAvatar,
        ]);
        break;

    case 'sessions':
        $getSessionCount = $db->prepare('
            SELECT COUNT(`session_id`)
            FROM `msz_sessions`
            WHERE `user_id` = :user_id
        ');
        $getSessionCount->bindValue('user_id', $app->getUserId());
        $sessionCount = $getSessionCount->execute() ? $getSessionCount->fetchColumn() : 0;

        $getSessions = $db->prepare('
            SELECT
                `session_id`, `session_country`, `user_agent`, `created_at`, `expires_on`,
                INET6_NTOA(`session_ip`) as `session_ip_decoded`
            FROM `msz_sessions`
            WHERE `user_id` = :user_id
            ORDER BY `session_id` DESC
            LIMIT :offset, :take
        ');
        $getSessions->bindValue('offset', $queryOffset);
        $getSessions->bindValue('take', $queryTake);
        $getSessions->bindValue('user_id', $app->getUserId());
        $sessions = $getSessions->execute() ? $getSessions->fetchAll() : [];

        $templating->vars([
            'active_session_id' => $app->getSessionId(),
            'user_sessions' => $sessions,
            'sessions_offset' => $queryOffset,
            'sessions_take' => $queryTake,
            'sessions_count' => $sessionCount,
        ]);
        break;

    case 'login-history':
        $getLoginAttemptsCount = $db->prepare('
            SELECT COUNT(`attempt_id`)
            FROM `msz_login_attempts`
            WHERE `user_id` = :user_id
        ');
        $getLoginAttemptsCount->bindValue('user_id', $app->getUserId());
        $loginAttemptsCount = $getLoginAttemptsCount->execute() ? $getLoginAttemptsCount->fetchColumn() : 0;

        $getLoginAttempts = $db->prepare('
            SELECT
                `attempt_id`, `attempt_country`, `was_successful`, `user_agent`, `created_at`,
                INET6_NTOA(`attempt_ip`) as `attempt_ip_decoded`
            FROM `msz_login_attempts`
            WHERE `user_id` = :user_id
            ORDER BY `attempt_id` DESC
            LIMIT :offset, :take
        ');
        $getLoginAttempts->bindValue('offset', $queryOffset);
        $getLoginAttempts->bindValue('take', $queryTake);
        $getLoginAttempts->bindValue('user_id', $app->getUserId());
        $loginAttempts = $getLoginAttempts->execute() ? $getLoginAttempts->fetchAll() : [];

        $templating->vars([
            'user_login_attempts' => $loginAttempts,
            'login_attempts_offset' => $queryOffset,
            'login_attempts_take' => $queryTake,
            'login_attempts_count' => $loginAttemptsCount,
        ]);
        break;
}

echo $templating->render("settings.{$settingsMode}");
