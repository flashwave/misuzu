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

        tpl_var('log_dump', print_r(audit_log_list(0, 50), true));
        echo tpl_render('manage.general.logs');
        break;

    case 'emoticons':
        if (!perms_check($generalPerms, MSZ_GENERAL_PERM_MANAGE_EMOTICONS)) {
            echo render_error(403);
            break;
        }

        echo tpl_render('manage.general.emoticons');
        break;

    case 'settings':
        if (!perms_check($generalPerms, MSZ_GENERAL_PERM_MANAGE_SETTINGS)) {
            echo render_error(403);
            break;
        }

        echo tpl_render('manage.general.settings');
        break;
}
