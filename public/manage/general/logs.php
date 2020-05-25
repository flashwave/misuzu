<?php
namespace Misuzu;

use Misuzu\AuditLog;
use Misuzu\Pagination;
use Misuzu\Users\User;

require_once '../../../misuzu.php';

if(!User::hasCurrent() || !perms_check_user(MSZ_PERMS_GENERAL, User::getCurrent()->getId(), MSZ_PERM_GENERAL_VIEW_LOGS)) {
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
