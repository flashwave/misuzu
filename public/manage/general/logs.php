<?php
namespace Misuzu;

use Misuzu\AuditLog;
use Misuzu\Pagination;

require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_GENERAL, user_session_current('user_id'), MSZ_PERM_GENERAL_VIEW_LOGS)) {
    echo render_error(403);
    return;
}

$pagination = new Pagination(AuditLog::countAll(), 50);

if(!$pagination->hasValidOffset()) {
    echo render_error(404);
    return;
}

$logs = AuditLog::all($pagination);

Template::render('manage.general.logs', [
    'global_logs' => $logs,
    'global_logs_pagination' => $pagination,
]);
