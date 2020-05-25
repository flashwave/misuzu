<?php
namespace Misuzu;

use Misuzu\AuditLog;
use Misuzu\Pagination;
use Misuzu\Users\User;
use Misuzu\Users\UserLoginAttempt;

require_once '../../misuzu.php';

$currentUser = User::getCurrent();

if($currentUser === null) {
    echo render_error(401);
    return;
}

$loginHistoryPagination = new Pagination(UserLoginAttempt::countAll($currentUser), 15, 'hp');
$accountLogPagination = new Pagination(AuditLog::countAll($currentUser), 15, 'ap');

Template::render('settings.logs', [
    'login_history_list' => UserLoginAttempt::all($loginHistoryPagination, $currentUser),
    'login_history_pagination' => $loginHistoryPagination,
    'account_log_list' => AuditLog::all($accountLogPagination, $currentUser),
    'account_log_pagination' => $accountLogPagination,
]);
