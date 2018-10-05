<?php
use Misuzu\Database;

require_once '../misuzu.php';

$queryOffset = (int)($_GET['o'] ?? 0);
$queryTake = 15;

$userPerms = perms_get_user(MSZ_PERMS_USER, user_session_current('user_id', 0));
$perms = [
    'edit_profile' => perms_check($userPerms, MSZ_PERM_USER_EDIT_PROFILE),
    'edit_avatar' => perms_check($userPerms, MSZ_PERM_USER_CHANGE_AVATAR),
    'edit_background' => perms_check($userPerms, MSZ_PERM_USER_CHANGE_BACKGROUND),
    'edit_about' => perms_check($userPerms, MSZ_PERM_USER_EDIT_ABOUT),
];

if (!user_session_active()) {
    echo render_error(403);
    return;
}

$settingsUserId = !empty($_REQUEST['user']) && perms_check($userPerms, MSZ_PERM_USER_MANAGE_USERS)
    ? (int)$_REQUEST['user']
    : user_session_current('user_id', 0);

if ($settingsUserId !== user_session_current('user_id', 0) && !user_exists($settingsUserId)) {
    echo render_error(400);
    return;
}

$settingsModes = [
    'account' => 'Account',
    'sessions' => 'Sessions',
    'logs' => 'Logs',
];
$settingsMode = $_GET['m'] ?? key($settingsModes);

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

$disableAccountOptions = !MSZ_DEBUG
    && boolval(config_get_default(false, 'Private', 'enabled'))
    && boolval(config_get_default(false, 'Private', 'disable_account_settings'));
$avatarFileName = "{$settingsUserId}.msz";
$avatarProps = user_avatar_default_options();
$backgroundProps = user_background_default_options();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify('settings', $_POST['csrf'] ?? '')) {
        $settingsErrors[] = MSZ_TMP_USER_ERROR_STRINGS['csrf'];
    } else {
        if (!empty($_POST['profile']) && is_array($_POST['profile'])) {
            if (!$perms['edit_profile']) {
                $settingsErrors[] = "You're not allowed to edit your profile.";
            } else {
                $setUserFieldErrors = user_profile_fields_set($settingsUserId, $_POST['profile']);

                if (count($setUserFieldErrors) > 0) {
                    foreach ($setUserFieldErrors as $name => $error) {
                        $settingsErrors[] = sprintf(
                            MSZ_TMP_USER_ERROR_STRINGS['profile'][$error] ?? MSZ_TMP_USER_ERROR_STRINGS['profile']['_'],
                            $name,
                            user_profile_field_get_display_name($name)
                        );
                    }
                }
            }
        }

        if (!empty($_POST['about']) && is_array($_POST['about'])) {
            if (!$perms['edit_about']) {
                $settingsErrors[] = "You're not allowed to edit your about page.";
            } else {
                $setAboutError = user_set_about_page(
                    $settingsUserId,
                    $_POST['about']['text'] ?? '',
                    (int)($_POST['about']['parser'] ?? MSZ_PARSER_PLAIN)
                );

                if ($setAboutError !== MSZ_USER_ABOUT_OK) {
                    $settingsErrors[] = sprintf(
                        MSZ_TMP_USER_ERROR_STRINGS['about'][$setAboutError] ?? MSZ_TMP_USER_ERROR_STRINGS['about']['_'],
                        MSZ_USER_ABOUT_MAX_LENGTH
                    );
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
                            MSZ_TMP_USER_ERROR_STRINGS['avatar']['upload'][$_FILES['avatar']['error']['file']]
                            ?? MSZ_TMP_USER_ERROR_STRINGS['avatar']['upload']['_'],
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
                            MSZ_TMP_USER_ERROR_STRINGS['avatar']['set'][$setAvatar]
                            ?? MSZ_TMP_USER_ERROR_STRINGS['avatar']['set']['_'],
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
                    user_background_set_settings($settingsUserId, MSZ_USER_BACKGROUND_ATTACHMENT_NONE);
                    break;

                case 'upload':
                    if (!$perms['edit_background']) {
                        $settingsErrors[] = "You aren't allow to change your background.";
                        break;
                    }

                    if (empty($_POST['background'])
                        || !is_array($_POST['background'])) {
                        break;
                    }

                    if (!empty($_FILES['background']['name']['file'])) {
                        if ($_FILES['background']['error']['file'] !== UPLOAD_ERR_OK) {
                            $settingsErrors[] = sprintf(
                                MSZ_TMP_USER_ERROR_STRINGS['avatar']['upload'][$_FILES['background']['error']['file']]
                                ?? MSZ_TMP_USER_ERROR_STRINGS['avatar']['upload']['_'],
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
                                MSZ_TMP_USER_ERROR_STRINGS['avatar']['set'][$setBackground]
                                ?? MSZ_TMP_USER_ERROR_STRINGS['avatar']['set']['_'],
                                $setBackground,
                                byte_symbol($backgroundProps['max_size'], true),
                                $backgroundProps['max_width'],
                                $backgroundProps['max_height']
                            );
                        }
                    }

                    $backgroundSettings = in_array($_POST['background']['attach'] ?? '', MSZ_USER_BACKGROUND_ATTACHMENTS_NAMES)
                        ? array_flip(MSZ_USER_BACKGROUND_ATTACHMENTS_NAMES)[$_POST['background']['attach']]
                        : MSZ_USER_BACKGROUND_ATTACHMENTS[0];

                    if (!empty($_POST['background']['attr']['blend'])) {
                        $backgroundSettings |= MSZ_USER_BACKGROUND_ATTRIBUTE_BLEND;
                    }

                    if (!empty($_POST['background']['attr']['slide'])) {
                        $backgroundSettings |= MSZ_USER_BACKGROUND_ATTRIBUTE_SLIDE;
                    }

                    user_background_set_settings($settingsUserId, $backgroundSettings);
                    break;
            }
        }

        if (!empty($_POST['session_action'])) {
            switch ($_POST['session_action']) {
                case 'kill-all':
                    user_session_purge_all($settingsUserId);
                    audit_log('PERSONAL_SESSION_DESTROY_ALL', $settingsUserId);
                    header('Location: /');
                    return;
            }
        }

        if (!empty($_POST['session']) && is_numeric($_POST['session'])) {
            $session = user_session_find((int)($_POST['session'] ?? 0));

            if (!$session) {
                $settingsErrors[] = 'Invalid session.';
            } elseif ((int)$session['user_id'] !== $settingsUserId) {
                $settingsErrors[] = 'You may only end your own sessions.';
            } elseif ((int)$session['session_id'] === user_session_current('session_id')) {
                header('Location: /auth.php?m=logout&s=' . csrf_token('logout'));
                return;
            } else {
                user_session_delete($session['session_id']);
                audit_log('PERSONAL_SESSION_DESTROY', $settingsUserId, [
                    $session['session_id'],
                ]);
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

    if (empty($settingsErrors) && !empty($_POST['user']) && !empty($_SERVER['HTTP_REFERER'])) {
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
                SELECT
                    %1$s, `email`, `user_about_content`, `user_about_parser`,
                    `user_background_settings` & 0x0F as `user_background_attachment`,
                    (`user_background_settings` & %2$d) > 0 as `user_background_attr_blend`,
                    (`user_background_settings` & %3$d) > 0 as `user_background_attr_slide`
                FROM `msz_users`
                WHERE `user_id` = :user_id
            ',
            pdo_prepare_array($profileFields, true, '`user_%s`'),
            MSZ_USER_BACKGROUND_ATTRIBUTE_BLEND,
            MSZ_USER_BACKGROUND_ATTRIBUTE_SLIDE
        ));
        $getAccountInfo->bindValue('user_id', $settingsUserId);
        $accountInfo = $getAccountInfo->execute() ? $getAccountInfo->fetch(PDO::FETCH_ASSOC) : [];

        $userHasAvatar = is_file(build_path(MSZ_STORAGE, 'avatars/original', $avatarFileName));
        $userHasBackground = is_file(build_path(MSZ_STORAGE, 'backgrounds/original', $avatarFileName));

        tpl_vars([
            'avatar' => $avatarProps,
            'background' => $backgroundProps,
            'user_has_avatar' => $userHasAvatar,
            'user_has_background' => $userHasBackground,
            'settings_profile_fields' => $profileFields,
            'settings_disable_account_options' => $disableAccountOptions,
            'account_info' => $accountInfo,
            'background_attachments' => MSZ_USER_BACKGROUND_ATTACHMENTS_NAMES,
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
            'active_session_id' => user_session_current('session_id'),
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
