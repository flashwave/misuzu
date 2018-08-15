<?php
require_once __DIR__ . '/../../misuzu.php';

$generalPerms = perms_get_user(MSZ_PERMS_GENERAL, $app->getUserId());

switch ($_GET['v'] ?? null) {
    default:
    case 'overview':
        echo tpl_render('manage.general.overview');
        break;

    case 'logs':
        if (!perms_check($generalPerms, MSZ_GENERAL_PERM_VIEW_LOGS)) {
            echo render_error(403);
            break;
        }

        var_dump(audit_log_list(0, 20));
        break;

    case 'emoticons':
        echo 'soon as well';
        break;

    case 'settings':
        echo 'somewhat soon i guess';
        break;
}
