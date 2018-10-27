<?php
require_once '../misuzu.php';

$queryOffset = (int)($_GET['o'] ?? 0);
$queryTake = 15;

if (!user_session_active()) {
    echo render_error(403);
    return;
}

$settingsUserId = user_session_current('user_id', 0);

tpl_vars([
    'settings_user_id' => $settingsUserId,
]);

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

                $fetchPassword = db_prepare('
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
                            $updateUser = db_prepare('
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
}

tpl_vars([
    'settings_errors' => $settingsErrors,
]);

$getAccountInfo = db_prepare(sprintf('
    SELECT `email`
    FROM `msz_users`
    WHERE `user_id` = :user_id
'));
$getAccountInfo->bindValue('user_id', $settingsUserId);
$accountInfo = $getAccountInfo->execute() ? $getAccountInfo->fetch(PDO::FETCH_ASSOC) : [];

tpl_vars([
    'background' => $backgroundProps,
    'settings_disable_account_options' => $disableAccountOptions,
    'account_info' => $accountInfo,
]);

$getSessionCount = db_prepare('
    SELECT COUNT(`session_id`)
    FROM `msz_sessions`
    WHERE `user_id` = :user_id
');
$getSessionCount->bindValue('user_id', $settingsUserId);
$sessionCount = $getSessionCount->execute() ? $getSessionCount->fetchColumn() : 0;

$getSessions = db_prepare('
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

$loginAttemptsOffset = max(0, $_GET['lo'] ?? 0);
$auditLogOffset = max(0, $_GET['ao'] ?? 0);

$getLoginAttemptsCount = db_prepare('
    SELECT COUNT(`attempt_id`)
    FROM `msz_login_attempts`
    WHERE `user_id` = :user_id
');
$getLoginAttemptsCount->bindValue('user_id', $settingsUserId);
$loginAttemptsCount = $getLoginAttemptsCount->execute() ? $getLoginAttemptsCount->fetchColumn() : 0;

$getLoginAttempts = db_prepare('
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

echo tpl_render('user.settings');
