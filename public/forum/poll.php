<?php
require_once '../../misuzu.php';

$redirect = !empty($_SERVER['HTTP_REFERER']) && empty($_SERVER['HTTP_X_MISUZU_XHR']) ? $_SERVER['HTTP_REFERER'] : '';
$isXHR = !$redirect;

if ($isXHR) {
    header('Content-Type: application/json; charset=utf-8');
} elseif (!is_local_url($redirect)) {
    echo render_info('Possible request forgery detected.', 403);
    return;
}

if (!csrf_verify('forum_poll', $_REQUEST['csrf'] ?? '')) {
    echo render_info_or_json($isXHR, "Couldn't verify this request, please refresh the page and try again.", 403);
    return;
}

if (!user_session_active()) {
    echo render_info_or_json($isXHR, 'You must be logged in to vote on polls.', 401);
    return;
}

$currentUserId = user_session_current('user_id', 0);

if (user_warning_check_expiration($currentUserId, MSZ_WARN_BAN) > 0) {
    echo render_info_or_json($isXHR, 'You have been banned, check your profile for more information.', 403);
    return;
}
if (user_warning_check_expiration($currentUserId, MSZ_WARN_SILENCE) > 0) {
    echo render_info_or_json($isXHR, 'You have been silenced, check your profile for more information.', 403);
    return;
}

header(csrf_http_header('forum_poll'));

if (empty($_POST['polls']) || !is_array($_POST['polls'])) {
    echo render_info_or_json($isXHR, "Invalid request.", 400);
    return;
}

foreach ($_POST['polls'] as $pollId => $answerId) {
    if (!is_int($pollId) || !is_string($answerId) || !ctype_digit($answerId)) {
        continue;
    }

    $answerId = (int)$answerId;

    var_dump($pollId, $answerId);
}
