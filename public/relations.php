<?php
use Misuzu\Database;

require_once __DIR__ . '/../misuzu.php';

if (empty($_SERVER['HTTP_REFERER']) || !is_local_url($_SERVER['HTTP_REFERER'])) {
    header('Location: /');
    return;
}

if (!$app->hasActiveSession()) {
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

        if (user_relation_add($app->getUserId(), $subjectId, $type) !== MSZ_USER_RELATION_E_OK) {
            echo render_error(500);
            return;
        }
        break;

    case 'remove':
        if (!user_relation_remove($app->getUserId(), $subjectId)) {
            echo render_error(500);
            return;
        }
        break;
}

header('Location: ' . $_SERVER['HTTP_REFERER']);
