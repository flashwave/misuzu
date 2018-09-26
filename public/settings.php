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
    'edit_background' => perms_check($userPerms, MSZ_PERM_USER_CHANGE_BACKGROUND),
    'edit_about' => perms_check($userPerms, MSZ_PERM_USER_EDIT_ABOUT),
];

if (!$app->hasActiveSession()) {
    echo render_error(403);
    return;
}

$settingsUserId = !empty($_REQUEST['user']) && perms_check($userPerms, MSZ_PERM_USER_MANAGE_USERS)
    ? (int)$_REQUEST['user']
    : $app->getUserId();

if ($settingsUserId !== $app->getUserId() && !user_exists($settingsUserId)) {
    echo render_error(400);
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
    'settings_user_id' => $settingsUserId,
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

$disableAccountOptions = !MSZ_DEBUG && $app->disableRegistration();
$avatarFileName = "{$settingsUserId}.msz";
$avatarProps = $app->getAvatarProps();
$backgroundProps = $app->getBackgroundProps();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!tmp_csrf_verify($_POST['csrf'] ?? '')) {
        $settingsErrors[] = $csrfErrorString;
    } else {
        if (!empty($_POST['profile']) && is_array($_POST['profile'])) {
            if (!$perms['edit_profile']) {
                $settingsErrors[] = "You're not allowed to edit your profile.";
            } else {
                $setUserFieldErrors = user_profile_fields_set($settingsUserId, $_POST['profile']);

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
        }

        if (!empty($_POST['about']) && is_array($_POST['about'])) {
            if (!$perms['edit_about']) {
                $settingsErrors[] = "You're not allowed to edit your about page.";
            } else {
                $aboutParser = (int)($_POST['about']['parser'] ?? MSZ_PARSER_PLAIN);
                $aboutText = $_POST['about']['text'] ?? '';

                // TODO: this is disgusting (move this into a user_set_about function or some shit)
                while (true) {
                    // TODO: take parser shit out of forum_post
                    if (!parser_is_valid($aboutParser)) {
                        $settingsErrors[] = 'Invalid parser specified.';
                        break;
                    }

                    if (strlen($aboutText) > 0xFFFF) {
                        $settingsErrors[] = 'Please keep the length of your about page to at most ' . 0xFFFF . '.';
                        break;
                    }

                    $setAbout = Database::prepare('
                        UPDATE `msz_users`
                        SET `user_about_content` = :content,
                            `user_about_parser` = :parser
                        WHERE `user_id` = :user
                    ');
                    $setAbout->bindValue('user', $settingsUserId);
                    $setAbout->bindValue('content', strlen($aboutText) < 1 ? null : $aboutText);
                    $setAbout->bindValue('parser', $aboutParser);
                    $setAbout->execute();
                    break;
                }
            }
        }

        if (!empty($_FILES['avatar'])) {
            if (empty($_POST['avatar']['mode'])) {
                // cool monkey patch
                $_POST['avatar']['mode'] = empty($_POST['avatar']['delete']) ? 'upload' : 'delete';
            }

            switch ($_POST['avatar']['mode'] ?? '') {
                case 'delete':
                    user_avatar_delete($settingsUserId);
                    break;

                case 'upload':
                    if (!$perms['edit_avatar']) {
                        $settingsErrors[] = "You aren't allow to change your avatar.";
                        break;
                    }

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
                            byte_symbol($avatarProps['max_size'], true),
                            $avatarProps['max_width'],
                            $avatarProps['max_height']
                        );
                        break;
                    }

                    $setAvatar = user_avatar_set_from_path(
                        $settingsUserId,
                        $_FILES['avatar']['tmp_name']['file'],
                        $avatarProps
                    );

                    if ($setAvatar !== MSZ_USER_AVATAR_NO_ERRORS) {
                        $settingsErrors[] = sprintf(
                            $avatarErrorStrings['set'][$setAvatar]
                            ?? $avatarErrorStrings['set']['default'],
                            $setAvatar,
                            byte_symbol($avatarProps['max_size'], true),
                            $avatarProps['max_width'],
                            $avatarProps['max_height']
                        );
                    }
                    break;
            }
        }

        if (!empty($_FILES['background'])) {
            switch ($_POST['background']['mode'] ?? '') {
                case 'delete':
                    user_background_delete($settingsUserId);
                    break;

                case 'upload':
                    if (!$perms['edit_background']) {
                        $settingsErrors[] = "You aren't allow to change your background.";
                        break;
                    }

                    if (empty($_FILES['background'])
                        || !is_array($_FILES['background'])
                        || empty($_FILES['background']['name']['file'])) {
                        break;
                    }

                    if ($_FILES['background']['error']['file'] !== UPLOAD_ERR_OK) {
                        $settingsErrors[] = sprintf(
                            $avatarErrorStrings['upload'][$_FILES['background']['error']['file']]
                            ?? $avatarErrorStrings['upload']['default'],
                            $_FILES['background']['error']['file'],
                            byte_symbol($backgroundProps['max_size'], true),
                            $backgroundProps['max_width'],
                            $backgroundProps['max_height']
                        );
                        break;
                    }

                    $setBackground = user_background_set_from_path(
                        $settingsUserId,
                        $_FILES['background']['tmp_name']['file'],
                        $backgroundProps
                    );

                    if ($setBackground !== MSZ_USER_BACKGROUND_NO_ERRORS) {
                        $settingsErrors[] = sprintf(
                            $avatarErrorStrings['set'][$setBackground]
                            ?? $avatarErrorStrings['set']['default'],
                            $setBackground,
                            byte_symbol($backgroundProps['max_size'], true),
                            $backgroundProps['max_width'],
                            $backgroundProps['max_height']
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
                        'user_id' => $settingsUserId,
                    ]);
                    audit_log('PERSONAL_SESSION_DESTROY_ALL', $settingsUserId);
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

                if (!$session || (int)$session['user_id'] !== $settingsUserId) {
                    $settingsErrors[] = 'You may only end your own sessions.';
                } else {
                    if ((int)$session['session_id'] === $app->getSessionId()) {
                        header('Location: /auth.php?m=logout&s=' . tmp_csrf_token());
                        return;
                    }

                    user_session_delete($session['session_id']);
                    audit_log('PERSONAL_SESSION_DESTROY', $settingsUserId, [
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
                $fetchPassword->bindValue('user_id', $settingsUserId);
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
                                    audit_log('PERSONAL_EMAIL_CHANGE', $settingsUserId, [
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
                                    audit_log('PERSONAL_PASSWORD_CHANGE', $settingsUserId);
                                }
                            }
                        }

                        if (count($updateAccountFields) > 0) {
                            $updateUser = Database::prepare('
                                UPDATE `msz_users`
                                SET ' . pdo_prepare_array_update($updateAccountFields, true) . '
                                WHERE `user_id` = :user_id
                            ');
                            $updateAccountFields['user_id'] = $settingsUserId;
                            $updateUser->execute($updateAccountFields);
                        }
                    }
                }
            }
        }
    }

    if (!empty($_POST['user']) && !empty($_SERVER['HTTP_REFERER'])) {
        header('Location: /profile.php?u=' . ((int)($_POST['user'] ?? 0)));
        return;
    }
}

tpl_vars([
    'settings_title' => $settingsModes[$settingsMode],
    'settings_errors' => $settingsErrors,
]);

switch ($settingsMode) {
    case 'account':
        $profileFields = user_profile_fields_get();

        $getAccountInfo = Database::prepare(sprintf(
            '
                SELECT %s, `email`, `user_about_content`, `user_about_parser`
                FROM `msz_users`
                WHERE `user_id` = :user_id
            ',
            pdo_prepare_array($profileFields, true, '`user_%s`')
        ));
        $getAccountInfo->bindValue('user_id', $settingsUserId);
        $accountInfo = $getAccountInfo->execute() ? $getAccountInfo->fetch(PDO::FETCH_ASSOC) : [];

        $userHasAvatar = is_file(build_path($app->getStoragePath(), 'avatars/original', $avatarFileName));
        $userHasBackground = is_file(build_path($app->getStoragePath(), 'backgrounds/original', $avatarFileName));

        tpl_vars([
            'avatar' => $avatarProps,
            'background' => $backgroundProps,
            'user_has_avatar' => $userHasAvatar,
            'user_has_background' => $userHasBackground,
            'settings_profile_fields' => $profileFields,
            'settings_disable_account_options' => $disableAccountOptions,
            'account_info' => $accountInfo,
        ]);
        break;

    case 'sessions':
        $getSessionCount = Database::prepare('
            SELECT COUNT(`session_id`)
            FROM `msz_sessions`
            WHERE `user_id` = :user_id
        ');
        $getSessionCount->bindValue('user_id', $settingsUserId);
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
        $getSessions->bindValue('user_id', $settingsUserId);
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
        $getLoginAttemptsCount->bindValue('user_id', $settingsUserId);
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
        $getLoginAttempts->bindValue('user_id', $settingsUserId);
        $loginAttempts = $getLoginAttempts->execute() ? $getLoginAttempts->fetchAll() : [];

        $auditLogCount = audit_log_count($settingsUserId);
        $auditLog = audit_log_list(
            $auditLogOffset,
            min(20, max(5, $queryTake)),
            $settingsUserId
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
