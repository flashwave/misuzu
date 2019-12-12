<?php
namespace Misuzu;

use Misuzu\Net\IPAddress;

require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_USER, user_session_current('user_id'), MSZ_PERM_USER_MANAGE_WARNINGS)) {
    echo render_error(403);
    return;
}

$notices = [];
$currentUserId = user_session_current('user_id');

if(!empty($_POST['lookup']) && is_string($_POST['lookup'])) {
    url_redirect('manage-users-warnings', ['user' => user_id_from_username($_POST['lookup'])]);
    return;
}

// instead of just kinda taking $_GET['w'] this should really fetch the info from the database
// and make sure that the user has authority
if(!empty($_GET['delete'])) {
    user_warning_remove((int)($_GET['w'] ?? 0));
    redirect($_SERVER['HTTP_REFERER'] ?? url('manage-users-warnings'));
    return;
}

if(!empty($_POST['warning']) && is_array($_POST['warning'])) {
    $warningType = (int)($_POST['warning']['type'] ?? 0);

    if(user_warning_type_is_valid($warningType)) {
        $warningDuration = 0;

        if(user_warning_has_duration($warningType)) {
            $duration = (int)($_POST['warning']['duration'] ?? 0);

            if($duration > 0) {
                $warningDuration = time() + $duration;
            } elseif($duration < 0) {
                $customDuration = $_POST['warning']['duration_custom'] ?? '';

                if(!empty($customDuration)) {
                    switch($duration) {
                        case -1: // YYYY-MM-DD
                            $splitDate = array_apply(explode('-', $customDuration, 3), function ($a) {
                                return (int)$a;
                            });

                            if(checkdate($splitDate[1], $splitDate[2], $splitDate[0])) {
                                $warningDuration = mktime(0, 0, 0, $splitDate[1], $splitDate[2], $splitDate[0]);
                            }
                            break;

                        case -2: // Raw seconds
                            $warningDuration = time() + (int)$customDuration;
                            break;

                        case -3: // strtotime
                            $warningDuration = strtotime($customDuration);
                            break;
                    }
                }
            }

            if($warningDuration <= time()) {
                $notices[] = 'The duration supplied was invalid.';
            }
        }

        $warningsUser = (int)($_POST['warning']['user'] ?? 0);

        if(!user_check_super($currentUserId) && !user_check_authority($currentUserId, $warningsUser)) {
            $notices[] = 'You do not have authority over this user.';
        }

        if(empty($notices) && $warningsUser > 0) {
            $warningId = user_warning_add(
                $warningsUser,
                user_get_last_ip($warningsUser),
                $currentUserId,
                IPAddress::remote(),
                $warningType,
                $_POST['warning']['note'],
                $_POST['warning']['private'],
                $warningDuration
            );
        }

        if(!empty($warningId) && $warningId < 0) {
            switch($warningId) {
                case MSZ_E_WARNING_ADD_DB:
                    $notices[] = 'Failed to record the warning in the database.';
                    break;

                case MSZ_E_WARNING_ADD_TYPE:
                    $notices[] = 'The warning type provided was invalid.';
                    break;

                case MSZ_E_WARNING_ADD_USER:
                    $notices[] = 'The User ID provided was invalid.';
                    break;

                case MSZ_E_WARNING_ADD_DURATION:
                    $notices[] = 'The duration specified was invalid.';
                    break;
            }
        }
    }
}

if(empty($warningsUser)) {
    $warningsUser = max(0, (int)($_GET['u'] ?? 0));
}

$warningsPagination = new Pagination(user_warning_global_count($warningsUser), 50);

if(!$warningsPagination->hasValidOffset()) {
    echo render_error(404);
    return;
}

$warningsList = user_warning_global_fetch(
    $warningsPagination->getOffset(),
    $warningsPagination->getRange(),
    $warningsUser
);

// calling array_flip since the input_select macro wants value => display, but this looks cuter
$warningDurations = array_flip([
    'Pick a duration...'    => 0,
    '5 Minutes'             => 60 * 5,
    '15 Minutes'            => 60 * 15,
    '30 Minutes'            => 60 * 30,
    '45 Minutes'            => 60 * 45,
    '1 Hour'                => 60 * 60,
    '2 Hours'               => 60 * 60 * 2,
    '3 Hours'               => 60 * 60 * 3,
    '6 Hours'               => 60 * 60 * 6,
    '12 Hours'              => 60 * 60 * 12,
    '1 Day'                 => 60 * 60 * 24,
    '2 Days'                => 60 * 60 * 24 * 2,
    '1 Week'                => 60 * 60 * 24 * 7,
    '2 Weeks'               => 60 * 60 * 24 * 7 * 2,
    '1 Month'               => 60 * 60 * 24 * 365 / 12,
    '3 Months'              => 60 * 60 * 24 * 365 / 12 * 3,
    '6 Months'              => 60 * 60 * 24 * 365 / 12 * 6,
    '9 Months'              => 60 * 60 * 24 * 365 / 12 * 9,
    '1 Year'                => 60 * 60 * 24 * 365,
    'Until (YYYY-MM-DD) ->' => -1,
    'Until (Seconds) ->'    => -2,
    'Until (strtotime) ->'  => -3,
]);

Template::render('manage.users.warnings', [
    'warnings' => [
        'notices' => $notices,
        'pagination' => $warningsPagination,
        'list' => $warningsList,
        'user_id' => $warningsUser,
        'username' => user_username_from_id($warningsUser),
        'types' => user_warning_get_types(),
        'durations' => $warningDurations,
    ],
]);
