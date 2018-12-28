<?php
require_once '../misuzu.php';

if (empty($_SERVER['HTTP_REFERER']) || !is_local_url($_SERVER['HTTP_REFERER'])) {
    header('Location: /');
    return;
}

if (!user_session_active()) {
    echo render_error(401);
    return;
}

if (user_warning_check_expiration(user_session_current('user_id', 0), MSZ_WARN_BAN) > 0) {
    echo render_error(403);
    return;
}

$subjectId = (int)($_GET['u'] ?? 0);

switch ($_GET['m'] ?? null) {
    case 'add':
        switch ($_GET['t'] ?? null) {
            case 'follow':
            default:
                $type = MSZ_USER_RELATION_FOLLOW;
                break;
        }

        if (user_relation_add(user_session_current('user_id', 0), $subjectId, $type) !== MSZ_USER_RELATION_E_OK) {
            echo render_error(500);
            return;
        }
        break;

    case 'remove':
        if (!user_relation_remove(user_session_current('user_id', 0), $subjectId)) {
            echo render_error(500);
            return;
        }
        break;
}

header('Location: ' . $_SERVER['HTTP_REFERER']);
