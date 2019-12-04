<?php
namespace Misuzu;

require_once '../../misuzu.php';

if(!user_session_active()) {
    echo render_error(401);
    return;
}

$errors = [];
$currentUserId = user_session_current('user_id');
$sessionActive = user_session_current('session_id');

if(!empty($_POST['session']) && csrf_verify_request()) {
    $currentSessionKilled = false;

    if(is_array($_POST['session'])) {
        foreach($_POST['session'] as $sessionId) {
            $sessionId = intval($sessionId);
            $session = user_session_find($sessionId);

            if(!$session || (int)$session['user_id'] !== $currentUserId) {
                $errors[] = "Session #{$sessionId} does not exist.";
                continue;
            } elseif((int)$session['session_id'] === $sessionActive) {
                $currentSessionKilled = true;
            }

            user_session_delete($session['session_id']);
            audit_log(MSZ_AUDIT_PERSONAL_SESSION_DESTROY, $currentUserId, [
                $session['session_id'],
            ]);
        }
    } elseif($_POST['session'] === 'all') {
        $currentSessionKilled = true;
        user_session_purge_all($currentUserId);
        audit_log(MSZ_AUDIT_PERSONAL_SESSION_DESTROY_ALL, $currentUserId);
    }

    if($currentSessionKilled) {
        url_redirect('index');
        return;
    }
}

$sessionPagination = pagination_create(user_session_count($currentUserId), 15);

if(!pagination_is_valid_offset(pagination_offset($sessionPagination, pagination_param()))) {
    $sessionPagination['offset'] = 0;
    $sessionPagination['page'] = 1;
}

$sessionList = user_session_list(
    $sessionPagination['offset'],
    $sessionPagination['range'],
    $currentUserId
);

Template::render('settings.sessions', [
    'errors' => $errors,
    'session_list' => $sessionList,
    'session_active_id' => $sessionActive,
    'session_pagination' => $sessionPagination,
]);
