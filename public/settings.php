<?php
require_once '../misuzu.php';

if (!user_session_active()) {
    echo render_error(403);
    return;
}

$errors = [];

$disableAccountOptions = !MSZ_DEBUG && (
    boolval(config_get_default(false, 'Private', 'enabled'))
    && boolval(config_get_default(false, 'Private', 'disable_account_settings'))
);

$currentEmail = user_email_get(user_session_current('user_id'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify('settings', $_POST['csrf'] ?? '')) {
        $errors[] = MSZ_TMP_USER_ERROR_STRINGS['csrf'];
    } else {
        if (!empty($_POST['session'])) {
            $currentSessionKilled = false;

            if (is_array($_POST['session'])) {
                foreach ($_POST['session'] as $sessionId) {
                    $sessionId = intval($sessionId);
                    $session = user_session_find($sessionId);

                    if (!$session || (int)$session['user_id'] !== user_session_current('user_id')) {
                        $errors[] = "Session #{$sessionId} does not exist.";
                        break;
                    } elseif ((int)$session['session_id'] === user_session_current('session_id')) {
                        $currentSessionKilled = true;
                    }

                    user_session_delete($session['session_id']);
                    audit_log('PERSONAL_SESSION_DESTROY', user_session_current('user_id'), [
                        $session['session_id'],
                    ]);
                }
            } elseif ($_POST['session'] === 'all') {
                $currentSessionKilled = true;
                user_session_purge_all(user_session_current('user_id'));
                audit_log('PERSONAL_SESSION_DESTROY_ALL', user_session_current('user_id'));
            }

            if ($currentSessionKilled) {
                header('Location: /');
                return;
            }
        }

        if (!$disableAccountOptions) {
            $currentPasswordValid = !empty($_POST['current_password']);

            if (!user_password_verify_db(user_session_current('user_id'), $_POST['current_password'] ?? '')) {
                $errors[] = 'Your password was incorrect.';
            } else {
                // Changing e-mail
                if (!empty($_POST['email']['new'])) {
                    if (empty($_POST['email']['confirm']) || $_POST['email']['new'] !== $_POST['email']['confirm']) {
                        $errors[] = 'The addresses you entered did not match each other.';
                    } elseif ($currentEmail === mb_strtolower($_POST['email']['confirm'])) {
                        $errors[] = 'This is already your e-mail address!';
                    } else {
                        $checkMail = user_validate_email($_POST['email']['new'], true);

                        if ($checkMail !== '') {
                            switch ($checkMail) {
                                case 'dns':
                                    $errors[] = 'No valid MX record exists for this domain.';
                                    break;

                                case 'format':
                                    $errors[] = 'The given e-mail address was incorrectly formatted.';
                                    break;

                                case 'in-use':
                                    $errors[] = 'This e-mail address is already in use.';
                                    break;

                                default:
                                    $errors[] = 'Unknown e-mail validation error.';
                            }
                        } else {
                            user_email_set(user_session_current('user_id'), $_POST['email']['new']);
                            audit_log('PERSONAL_EMAIL_CHANGE', user_session_current('user_id'), [
                                $_POST['email']['new'],
                            ]);
                        }
                    }
                }

                // Changing password
                if (!empty($_POST['password']['new'])) {
                    if (empty($_POST['password']['confirm']) || $_POST['password']['new'] !== $_POST['password']['confirm']) {
                        $errors[] = 'The new passwords you entered did not match each other.';
                    } else {
                        $checkPassword = user_validate_password($_POST['password']['new']);

                        if ($checkPassword !== '') {
                            $errors[] = 'The given passwords was too weak.';
                        } else {
                            user_password_set(user_session_current('user_id'), $_POST['password']['new']);
                            audit_log('PERSONAL_PASSWORD_CHANGE', user_session_current('user_id'));
                        }
                    }
                }
            }
        }
    }
}

tpl_add_filter('log_format', function (string $text, string $json): string {
    return vsprintf($text, json_decode($json));
});

$sessions = [
    'list' => [],
    'active' => user_session_current('session_id'),
    'amount' => user_session_count(user_session_current('user_id')),
    'offset' => max(0, intval($_GET['sessions']['offset'] ?? 0)),
    'take' => clamp($_GET['sessions']['take'] ?? 15, 5, 30),
];

$logins = [
    'list' => [],
    'amount' => user_login_attempts_count(user_session_current('user_id')),
    'offset' => max(0, intval($_GET['logins']['offset'] ?? 0)),
    'take' => clamp($_GET['logins']['take'] ?? 15, 5, 30),
];

$logs = [
    'list' => [],
    'amount' => audit_log_count(user_session_current('user_id')),
    'offset' => max(0, intval($_GET['logs']['offset'] ?? 0)),
    'take' => clamp($_GET['logs']['take'] ?? 15, 5, 30),
    'strings' => [
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
];

$sessions['list'] = user_session_list($sessions['offset'], $sessions['take'], user_session_current('user_id'));
$logins['list'] = user_login_attempts_list($sessions['offset'], $sessions['take'], user_session_current('user_id'));
$logs['list'] = audit_log_list($logs['offset'], $logs['take'], user_session_current('user_id'));

$getUserRoles = db_prepare('
    SELECT r.`role_id`, r.`role_name`
    FROM `msz_user_roles` as ur
    LEFT JOIN `msz_roles` as r
    ON r.`role_id` = ur.`role_id`
    WHERE ur.`user_id` = :user_id
');
$getUserRoles->bindValue('user_id', user_session_current('user_id'));
$userRoles = $getUserRoles->execute() ? $getUserRoles->fetchAll(PDO::FETCH_ASSOC) : [];

var_dump($userRoles);

if (empty($errors)) { // delete this in 2019
    $errors[] = 'A few of the elements on this page have been moved to the on-profile editor. To find them, go to your profile and hit the "Edit Profile" button below your avatar.';
}

echo tpl_render('user.settings', [
    'errors' => $errors,
    'disable_account_options' => $disableAccountOptions,
    'current_email' => $currentEmail,
    'sessions' => $sessions,
    'logins' => $logins,
    'logs' => $logs,
    'roles' => $userRoles,
]);
