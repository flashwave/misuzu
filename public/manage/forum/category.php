<?php
require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_GENERAL, user_session_current('user_id'), MSZ_PERM_FORUM_MANAGE_FORUMS)) {
    echo render_error(403);
    return;
}

$getForum = db_prepare('
    SELECT *
    FROM `msz_forum_categories`
    WHERE `forum_id` = :forum_id
');
$getForum->bindValue('forum_id', (int)($_GET['f'] ?? 0));
$forum = db_fetch($getForum);

if(!$forum) {
    echo render_error(404);
    return;
}

echo tpl_render('manage.forum.forum', compact('forum'));
