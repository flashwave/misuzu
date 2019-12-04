<?php
namespace Misuzu;

require_once '../../misuzu.php';

if(!user_session_active()) {
    echo render_error(401);
    return;
}

$currentUserId = user_session_current('user_id');
$loginHistoryPagination = pagination_create(user_login_attempts_count($currentUserId), 15);
$accountLogPagination = pagination_create(audit_log_count($currentUserId), 15);

if(!pagination_is_valid_offset(pagination_offset($loginHistoryPagination, pagination_param('hp')))) {
    $loginHistoryPagination['offset'] = 0;
    $loginHistoryPagination['page'] = 1;
}

if(!pagination_is_valid_offset(pagination_offset($accountLogPagination, pagination_param('ap')))) {
    $accountLogPagination['offset'] = 0;
    $accountLogPagination['page'] = 1;
}

$loginHistoryList = user_login_attempts_list(
    $loginHistoryPagination['offset'],
    $loginHistoryPagination['range'],
    $currentUserId
);

$accountLogList = audit_log_list(
    $accountLogPagination['offset'],
    $accountLogPagination['range'],
    $currentUserId
);

Template::render('settings.logs', [
    'login_history_list' => $loginHistoryList,
    'login_history_pagination' => $loginHistoryPagination,
    'account_log_list' => $accountLogList,
    'account_log_pagination' => $accountLogPagination,
    'account_log_strings' => MSZ_AUDIT_LOG_STRINGS,
]);
