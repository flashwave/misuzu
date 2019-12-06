<?php
namespace Misuzu;

require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_GENERAL, user_session_current('user_id'), General::PERM_VIEW_LOGS)) {
    echo render_error(403);
    return;
}

$logsPagination = new Pagination(audit_log_count(), 50);

if(!$logsPagination->hasValidOffset()) {
    echo render_error(404);
    return;
}

$logs = audit_log_list(
    $logsPagination->getOffset(),
    $logsPagination->getRange()
);

Template::render('manage.general.logs', [
    'global_logs' => $logs,
    'global_logs_pagination' => $logsPagination,
    'global_logs_strings' => MSZ_AUDIT_LOG_STRINGS,
]);
