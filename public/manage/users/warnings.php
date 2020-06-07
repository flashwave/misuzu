<?php
namespace Misuzu;

use InvalidArgumentException;
use Misuzu\Net\IPAddress;
use Misuzu\Users\User;
use Misuzu\Users\UserNotFoundException;
use Misuzu\Users\UserWarning;
use Misuzu\Users\UserWarningNotFoundException;
use Misuzu\Users\UserWarningCreationFailedException;

require_once '../../../misuzu.php';

if(!User::hasCurrent() || !perms_check_user(MSZ_PERMS_USER, User::getCurrent()->getId(), MSZ_PERM_USER_MANAGE_WARNINGS)) {
    echo render_error(403);
    return;
}

$notices = [];
$currentUser = User::getCurrent();
$currentUserId = $currentUser->getId();

if(!empty($_POST['lookup']) && is_string($_POST['lookup'])) {
    try {
        $userId = User::byUsername((string)filter_input(INPUT_POST, 'lookup', FILTER_SANITIZE_STRING))->getId();
    } catch(UserNotFoundException $ex) {
        $userId = 0;
    }
    url_redirect('manage-users-warnings', ['user' => $userId]);
    return;
}

// instead of just kinda taking $_GET['w'] this should really fetch the info from the database
// and make sure that the user has authority
if(!empty($_GET['delete'])) {
    try {
        UserWarning::byId((int)filter_input(INPUT_GET, 'w', FILTER_SANITIZE_NUMBER_INT))->delete();
    } catch(UserWarningNotFoundException $ex) {}
    redirect($_SERVER['HTTP_REFERER'] ?? url('manage-users-warnings'));
    return;
}

if(!empty($_POST['warning']) && is_array($_POST['warning'])) {
    $warningType = (int)($_POST['warning']['type'] ?? 0);
    $warningDuration = 0;
    $warningDuration = (int)($_POST['warning']['duration'] ?? 0);

    if($warningDuration < -1) {
        $customDuration = $_POST['warning']['duration_custom'] ?? '';

        if(!empty($customDuration)) {
            switch($warningDuration) {
                case -100: // YYYY-MM-DD
                    $splitDate = array_apply(explode('-', $customDuration, 3), function ($a) {
                        return (int)$a;
                    });

                    if(checkdate($splitDate[1], $splitDate[2], $splitDate[0]))
                        $warningDuration = mktime(0, 0, 0, $splitDate[1], $splitDate[2], $splitDate[0]) - time();
                    break;

                case -200: // Raw seconds
                    $warningDuration = (int)$customDuration;
                    break;

                case -300: // strtotime
                    $warningDuration = strtotime($customDuration) - time();
                    break;
            }
        }
    }

    try {
        $warningsUserInfo = User::byId((int)($_POST['warning']['user'] ?? 0));
        $warningsUser = $warningsUserInfo->getId();

        if(!$currentUser->hasAuthorityOver($warningsUserInfo))
            $notices[] = 'You do not have authority over this user.';
    } catch(UserNotFoundException $ex) {
        $notices[] = 'This user doesn\'t exist.';
    }


    if(empty($notices) && !empty($warningsUserInfo)) {
        try {
            $warningInfo = UserWarning::create(
                $warningsUserInfo,
                $currentUser,
                $warningType,
                $warningDuration,
                $_POST['warning']['note'],
                $_POST['warning']['private']
            );
        } catch(InvalidArgumentException $ex) {
            $notices[] = $ex->getMessage();
        } catch(UserWarningCreationFailedException $ex) {
            $notices[] = 'Warning creation failed.';
        }
    }
}

if(empty($warningsUser))
    $warningsUser = max(0, (int)($_GET['u'] ?? 0));

if(empty($warningsUserInfo))
    try {
        $warningsUserInfo = User::byId($warningsUser);
    } catch(UserNotFoundException $ex) {
        $warningsUserInfo = null;
    }

$warningsPagination = new Pagination(UserWarning::countAll($warningsUserInfo), 10);

if(!$warningsPagination->hasValidOffset()) {
    echo render_error(404);
    return;
}

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
    'Permanent'             => -1,
    'Until (YYYY-MM-DD) ->' => -100,
    'Until (Seconds) ->'    => -200,
    'Until (strtotime) ->'  => -300,
]);

Template::render('manage.users.warnings', [
    'warnings' => [
        'notices' => $notices,
        'pagination' => $warningsPagination,
        'list' => UserWarning::all($warningsUserInfo, $warningsPagination),
        'user' => $warningsUserInfo,
        'durations' => $warningDurations,
        'types' => [
            UserWarning::TYPE_NOTE => 'Note',
            UserWarning::TYPE_WARN => 'Warning',
            UserWarning::TYPE_MUTE => 'Silence',
            UserWarning::TYPE_BAHN => 'Ban',
        ],
    ],
]);
