<?php
namespace Misuzu;

require_once '../../misuzu.php';

if(!user_session_active()) {
    echo render_error(401);
    return;
}

$currentUserId = user_session_current('user_id');
$loginHistoryPagination = new Pagination(user_login_attempts_count($currentUserId), 15);
$accountLogPagination = new Pagination(audit_log_count($currentUserId), 15);

$loginHistoryList = user_login_attempts_list(
    $loginHistoryPagination->getOffset(),
    $loginHistoryPagination->getRange(),
    $currentUserId
);

$accountLogList = audit_log_list(
    $accountLogPagination->getOffset(),
    $accountLogPagination->getRange(),
    $currentUserId
);

Template::render('settings.logs', [
    'login_history_list' => $loginHistoryList,
    'login_history_pagination' => $loginHistoryPagination,
    'account_log_list' => $accountLogList,
    'account_log_pagination' => $accountLogPagination,
    'account_log_strings' => MSZ_AUDIT_LOG_STRINGS,
]);
