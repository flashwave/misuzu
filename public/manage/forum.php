<?php
require_once '../../misuzu.php';

switch ($_GET['v'] ?? null) {
    case 'listing':
        $forums = db_query('SELECT * FROM `msz_forum_categories`');
        $rawPerms = forum_perms_create();
        $perms = manage_forum_perms_list($rawPerms);

        if (!empty($_POST['perms']) && is_array($_POST['perms'])) {
            $finalPerms = manage_perms_apply($perms, $_POST['perms'], $rawPerms);
            $perms = manage_forum_perms_list($finalPerms);
            tpl_var('calculated_perms', $finalPerms);
        }

        echo tpl_render('manage.forum.listing', compact('forums', 'perms'));
        break;

    case 'forum':
        $getForum = db_prepare('
            SELECT *
            FROM `msz_forum_categories`
            WHERE `forum_id` = :forum_id
        ');
        $getForum->bindValue('forum_id', (int)($_GET['f'] ?? 0));
        $forum = db_fetch($getForum);

        if (!$forum) {
            echo render_error(404);
            break;
        }

        echo tpl_render('manage.forum.forum', compact('forum'));
        break;
}
