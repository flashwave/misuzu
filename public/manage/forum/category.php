<?php
namespace Misuzu;

use Misuzu\Users\User;

require_once '../../../misuzu.php';

if(!User::hasCurrent() || !perms_check_user(MSZ_PERMS_GENERAL, User::getCurrent()->getId(), MSZ_PERM_FORUM_MANAGE_FORUMS)) {
    echo render_error(403);
    return;
}

$getForum = DB::prepare('
    SELECT *
    FROM `msz_forum_categories`
    WHERE `forum_id` = :forum_id
');
$getForum->bind('forum_id', (int)($_GET['f'] ?? 0));
$forum = $getForum->fetch();

if(!$forum) {
    echo render_error(404);
    return;
}

Template::render('manage.forum.forum', compact('forum'));
