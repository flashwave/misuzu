<?php
use Misuzu\Database;
use Misuzu\IO\File;

require_once __DIR__ . '/../misuzu.php';

$queryOffset = (int)($_GET['o'] ?? 0);
$queryTake = 15;

$userPerms = perms_get_user(MSZ_PERMS_USER, $app->getUserId());
$perms = [
    'edit_profile' => perms_check($userPerms, MSZ_PERM_USER_EDIT_PROFILE),
    'edit_avatar' => perms_check($userPerms, MSZ_PERM_USER_CHANGE_AVATAR),
];

if (!$app->hasActiveSession()) {
    echo render_error(403);
    return;
}

$settingsModes = [
    'account' => 'Account',
    'sessions' => 'Sessions',
    'logs' => 'Logs',
];
$settingsMode = $_GET['m'] ?? key($settingsModes);

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

tpl_vars([
    'settings_perms' => $perms,
    'settings_mode' => $settingsMode,
    'settings_modes' => $settingsModes,
]);

if (!array_key_exists($settingsMode, $settingsModes)) {
    http_response_code(404);
    tpl_var('settings_title', 'Not Found');
    echo tpl_render('settings.notfound');
    return;
}

$settingsErrors = [];

$disableAccountOptions = !$app->inDebugMode() && $app->disableRegistration();
$avatarFileName = "{$app->getUserId()}.msz";
$avatarProps = $app->getAvatarProps();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!tmp_csrf_verify($_POST['csrf'] ?? '')) {
        $settingsErrors[] = $csrfErrorString;
    } else {
        if (!empty($_POST['profile']) && is_array($_POST['profile'])) {
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

        if (!empty($_POST['avatar']) && is_array($_POST['avatar'])) {
            switch ($_POST['avatar']['mode'] ?? '') {
                case 'delete':
                    user_avatar_delete($app->getUserId());
                    break;

                case 'upload':
                    if (empty($_FILES['avatar'])
                    || !is_array($_FILES['avatar'])
                    || empty($_FILES['avatar']['name']['file'])) {
                        break;
                    }

                    if ($_FILES['avatar']['error']['file'] !== UPLOAD_ERR_OK) {
                        $settingsErrors[] = sprintf(
                            $avatarErrorStrings['upload'][$_FILES['avatar']['error']['file']]
                            ?? $avatarErrorStrings['upload']['default'],
                            $_FILES['avatar']['error']['file'],
                            byte_symbol($avatarProps['max_filesize'], true),
                            $avatarProps['max_width'],
                            $avatarProps['max_height']
                        );
                        break;
                    }

                    $setAvatar = user_avatar_set_from_path(
                        $app->getUserId(),
                        $_FILES['avatar']['tmp_name']['file']
                    );

                    if ($setAvatar !== MSZ_USER_AVATAR_NO_ERRORS) {
                        $settingsErrors[] = sprintf(
                            $avatarErrorStrings['set'][$setAvatar]
                            ?? $avatarErrorStrings['set']['default'],
                            $setAvatar,
                            byte_symbol($avatarProps['max_filesize'], true),
                            $avatarProps['max_width'],
                            $avatarProps['max_height']
                        );
                    }
                    break;
            }
        }

        if (!empty($_POST['session_action'])) {
            switch ($_POST['session_action']) {
                case 'kill-all':
                    Database::prepare('
                        DELETE FROM `msz_sessions`
                        WHERE `user_id` = :user_id
                    ')->execute([
                        'user_id' => $app->getUserId(),
                    ]);
                    audit_log('PERSONAL_SESSION_DESTROY_ALL', $app->getUserId());
                    header('Location: /');
                    return;
            }
        }

        if (!empty($_POST['session']) && is_numeric($_POST['session'])) {
            $session_id = (int)($_POST['session'] ?? 0);

            if ($session_id < 1) {
                $settingsErrors[] = 'Invalid session.';
            } else {
                $findSession = Database::prepare('
                    SELECT `session_id`, `user_id`
                    FROM `msz_sessions`
                    WHERE `session_id` = :session_id
                ');
                $findSession->bindValue('session_id', $session_id);
                $session = $findSession->execute() ? $findSession->fetch() : null;

                if (!$session || (int)$session['user_id'] !== $app->getUserId()) {
                    $settingsErrors[] = 'You may only end your own sessions.';
                } else {
                    if ((int)$session['session_id'] === $app->getSessionId()) {
                        header('Location: /auth.php?m=logout&s=' . tmp_csrf_token());
                        return;
                    }

                    user_session_delete($session['session_id']);
                    audit_log('PERSONAL_SESSION_DESTROY', $app->getUserId(), [
                        $session['session_id'],
                    ]);
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

                $fetchPassword = Database::prepare('
                SELECT `password`
                FROM `msz_users`
                WHERE `user_id` = :user_id
            ');
                $fetchPassword->bindValue('user_id', $app->getUserId());
                $currentPassword = $fetchPassword->execute() ? $fetchPassword->fetchColumn() : null;

                if (empty($currentPassword)) {
                    $settingsErrors[] = 'Something went horribly wrong.';
                } else {
                    if (!password_verify($_POST['current_password'], $currentPassword)) {
                        $settingsErrors[] = 'Your current password was incorrect.';
                    } else {
                        if (!empty($_POST['email']['new'])) {
                            if (empty($_POST['email']['confirm'])
                            || $_POST['email']['new'] !== $_POST['email']['confirm']) {
                                $settingsErrors[] = 'The given e-mail addresses did not match.';
                            } else {
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
                                            $settingsErrors[] = 'This e-mail address is already in use.';
                                            break;

                                        default:
                                            $settingsErrors[] = 'Unknown e-mail validation error.';
                                    }
                                } else {
                                    $updateAccountFields['email'] = mb_strtolower($_POST['email']['new']);
                                    audit_log('PERSONAL_EMAIL_CHANGE', $app->getUserId(), [
                                        $updateAccountFields['email'],
                                    ]);
                                }
                            }
                        }

                        if (!empty($_POST['password']['new'])) {
                            if (empty($_POST['password']['confirm'])
                            || $_POST['password']['new'] !== $_POST['password']['confirm']) {
                                $settingsErrors[] = "The given passwords did not match.";
                            } else {
                                $password_validate = user_validate_password($_POST['password']['new']);

                                if ($password_validate !== '') {
                                    $settingsErrors[] = "The given passwords was too weak.";
                                } else {
                                    $updateAccountFields['password'] = user_password_hash($_POST['password']['new']);
                                    audit_log('PERSONAL_PASSWORD_CHANGE', $app->getUserId());
                                }
                            }
                        }

                        if (count($updateAccountFields) > 0) {
                            $updateUser = Database::prepare('
                                UPDATE `msz_users`
                                SET ' . pdo_prepare_array_update($updateAccountFields, true) . '
                                WHERE `user_id` = :user_id
                            ');
                            $updateAccountFields['user_id'] = $app->getUserId();
                            $updateUser->execute($updateAccountFields);
                        }
                    }
                }
            }
        }
    }
}

tpl_vars([
    'settings_title' => $settingsModes[$settingsMode],
    'settings_errors' => $settingsErrors,
]);

switch ($settingsMode) {
    case 'account':
        $profileFields = user_profile_fields_get();
        $getUserFields = Database::prepare('
            SELECT ' . pdo_prepare_array($profileFields, true, '`user_%s`') . '
            FROM `msz_users`
            WHERE `user_id` = :user_id
        ');
        $getUserFields->bindValue('user_id', $app->getUserId());
        $userFields = $getUserFields->execute() ? $getUserFields->fetch() : [];

        $getMail = Database::prepare('
            SELECT `email`
            FROM `msz_users`
            WHERE `user_id` = :user_id
        ');
        $getMail->bindValue('user_id', $app->getUserId());
        $currentEmail = $getMail->execute() ? $getMail->fetchColumn() : 'Failed to fetch e-mail address.';
        $userHasAvatar = is_file(build_path($app->getStoragePath(), 'avatars/original', $avatarFileName));

        tpl_vars([
            'avatar_user_id' => $app->getUserId(),
            'avatar_max_width' => $avatarProps['max_width'],
            'avatar_max_height' => $avatarProps['max_height'],
            'avatar_max_filesize' => $avatarProps['max_filesize'],
            'user_has_avatar' => $userHasAvatar,
            'settings_profile_fields' => $profileFields,
            'settings_profile_values' => $userFields,
            'settings_disable_account_options' => $disableAccountOptions,
            'settings_email' => $currentEmail,
        ]);
        break;

    case 'sessions':
        $getSessionCount = Database::prepare('
            SELECT COUNT(`session_id`)
            FROM `msz_sessions`
            WHERE `user_id` = :user_id
        ');
        $getSessionCount->bindValue('user_id', $app->getUserId());
        $sessionCount = $getSessionCount->execute() ? $getSessionCount->fetchColumn() : 0;

        $getSessions = Database::prepare('
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

        tpl_vars([
            'active_session_id' => $app->getSessionId(),
            'user_sessions' => $sessions,
            'sessions_offset' => $queryOffset,
            'sessions_take' => $queryTake,
            'sessions_count' => $sessionCount,
        ]);
        break;

    case 'logs':
        $loginAttemptsOffset = max(0, $_GET['lo'] ?? 0);
        $auditLogOffset = max(0, $_GET['ao'] ?? 0);

        $getLoginAttemptsCount = Database::prepare('
            SELECT COUNT(`attempt_id`)
            FROM `msz_login_attempts`
            WHERE `user_id` = :user_id
        ');
        $getLoginAttemptsCount->bindValue('user_id', $app->getUserId());
        $loginAttemptsCount = $getLoginAttemptsCount->execute() ? $getLoginAttemptsCount->fetchColumn() : 0;

        $getLoginAttempts = Database::prepare('
            SELECT
                `attempt_id`, `attempt_country`, `was_successful`, `user_agent`, `created_at`,
                INET6_NTOA(`attempt_ip`) as `attempt_ip_decoded`
            FROM `msz_login_attempts`
            WHERE `user_id` = :user_id
            ORDER BY `attempt_id` DESC
            LIMIT :offset, :take
        ');
        $getLoginAttempts->bindValue('offset', $loginAttemptsOffset);
        $getLoginAttempts->bindValue('take', min(20, max(5, $queryTake)));
        $getLoginAttempts->bindValue('user_id', $app->getUserId());
        $loginAttempts = $getLoginAttempts->execute() ? $getLoginAttempts->fetchAll() : [];

        $auditLogCount = audit_log_count($app->getUserId());
        $auditLog = audit_log_list(
            $auditLogOffset,
            min(20, max(5, $queryTake)),
            $app->getUserId()
        );

        tpl_vars([
            'audit_logs' => $auditLog,
            'audit_log_count' => $auditLogCount,
            'audit_log_take' => $queryTake,
            'audit_log_offset' => $auditLogOffset,
            'log_strings' => [
                'PERSONAL_EMAIL_CHANGE' => 'Changed e-mail address to %s.',
                'PERSONAL_PASSWORD_CHANGE' => 'Changed account password.',
                'PERSONAL_SESSION_DESTROY' => 'Ended session #%d.',
                'PERSONAL_SESSION_DESTROY_ALL' => 'Ended all personal sessions.',
                'PASSWORD_RESET' => 'Successfully used the password reset form to change password.',
                'CHANGELOG_ENTRY_CREATE' => 'Created a new changelog entry #%d.',
                'CHANGELOG_ENTRY_EDIT' => 'Edited changelog entry #%d.',
                'CHANGELOG_TAG_ADD' => 'Added tag #%2$d to changelog entry #%1$d.',
                'CHANGELOG_TAG_REMOVE' => 'Removed tag #%2$d from changelog entry #%1$d.',
                'CHANGELOG_TAG_CREATE' => 'Created new changelog tag #%d.',
                'CHANGELOG_TAG_EDIT' => 'Edited changelog tag #%d.',
                'CHANGELOG_ACTION_CREATE' => 'Created new changelog action #%d.',
                'CHANGELOG_ACTION_EDIT' => 'Edited changelog action #%d.',
            ],
            'user_login_attempts' => $loginAttempts,
            'login_attempts_offset' => $loginAttemptsOffset,
            'login_attempts_take' => $queryTake,
            'login_attempts_count' => $loginAttemptsCount,
        ]);
        break;
}

echo tpl_render("settings.{$settingsMode}");
