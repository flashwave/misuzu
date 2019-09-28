<?php
namespace Misuzu;

require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_GENERAL, user_session_current('user_id'), MSZ_PERM_GENERAL_VIEW_LOGS)) {
    echo render_error(403);
    return;
}

$logsPagination = pagination_create(audit_log_count(), 50);
$logsOffset = pagination_offset($logsPagination, pagination_param());

if(!pagination_is_valid_offset($logsOffset)) {
    echo render_error(404);
    return;
}

$logs = audit_log_list($logsOffset, $logsPagination['range']);

echo tpl_render('manage.general.logs', [
    'global_logs' => $logs,
    'global_logs_pagination' => $logsPagination,
    'global_logs_strings' => MSZ_AUDIT_LOG_STRINGS,
]);
