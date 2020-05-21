<?php
namespace Misuzu;

use Misuzu\AuditLog;
use Misuzu\Pagination;
use Misuzu\Users\User;

require_once '../../misuzu.php';

if(!user_session_active()) {
    echo render_error(401);
    return;
}

$currentUser = User::getCurrent();
$loginHistoryPagination = new Pagination(user_login_attempts_count($currentUser->getId()), 15, 'hp');
$accountLogPagination = new Pagination(AuditLog::countAll($currentUser), 15, 'ap');

$loginHistoryList = user_login_attempts_list(
    $loginHistoryPagination->getOffset(),
    $loginHistoryPagination->getRange(),
    $currentUser->getId()
);

Template::render('settings.logs', [
    'login_history_list' => $loginHistoryList,
    'login_history_pagination' => $loginHistoryPagination,
    'account_log_list' => AuditLog::all($accountLogPagination, $currentUser),
    'account_log_pagination' => $accountLogPagination,
]);
