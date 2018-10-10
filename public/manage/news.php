<?php
require_once '../../misuzu.php';

$newsPerms = perms_get_user(MSZ_PERMS_NEWS, user_session_current('user_id', 0));

switch ($_GET['v'] ?? null) {
    case 'posts':
        if (!perms_check($newsPerms, MSZ_PERM_NEWS_MANAGE_POSTS)) {
            echo render_error(403);
            break;
        }

        echo 'posts';
        break;

    case 'categories':
        if (!perms_check($newsPerms, MSZ_PERM_NEWS_MANAGE_CATEGORIES)) {
            echo render_error(403);
            break;
        }

        $catTake = 15;
        $catOffset = (int)($_GET['o'] ?? 0);
        $cats = news_categories_get($catOffset, $catTake);

        echo 'cats';
        var_dump($cats);
        break;
}
