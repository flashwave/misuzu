<?php
use Misuzu\Database;

require_once '../../misuzu.php';

switch ($_GET['v'] ?? null) {
    case 'listing':
        $forums = db_query('SELECT * FROM `msz_forum_categories`');

        echo tpl_render('manage.forum.listing', compact('forums'));
        break;

    case 'forum':
        $getForum = db_prepare('
            SELECT *
            FROM `msz_forum_categories`
            WHERE `forum_id` = :forum_id
        ');
        $getForum->bindValue('forum_id', (int)($_GET['f'] ?? 0));
        $forum = $getForum->execute() ? $getForum->fetch(PDO::FETCH_ASSOC) : false;

        if (!$forum) {
            echo render_error(404);
            break;
        }

        $roles = db_query('SELECT `role_id`, `role_name` FROM `msz_roles`')->fetchAll(PDO::FETCH_ASSOC);
        $perms = manage_forum_perms_list(forum_perms_get_role_raw($forum['forum_id'], null));

        echo tpl_render('manage.forum.forum', compact('forum', 'roles', 'perms'));
        break;

    case 'forumperms':
        $getRole = db_prepare('
            SELECT `role_id`, `role_name`
            FROM `msz_roles`
            WHERE `role_id` = :role_id
        ');
        $getRole->bindValue('role_id', (int)($_GET['r'] ?? 0));
        $role = $getRole->execute() ? $getRole->fetch(PDO::FETCH_ASSOC) : false;

        if (!$role) {
            echo render_error(404);
            break;
        }

        $forumId = empty($_GET['f']) ? null : (int)($_GET['f'] ?? 0);

        if ($forumId) {
            $getForum = db_prepare('
                SELECT `forum_name`
                FROM `msz_forum_categories`
                WHERE `forum_id` = :forum_id
            ');
            $getForum->bindValue('forum_id', $forumId);
            $forum = $getForum->execute() ? $getForum->fetch(PDO::FETCH_ASSOC) : false;

            if (!$forum) {
                echo render_error(404);
                break;
            }

            tpl_var('forum', $forum);
        }

        $perms = manage_forum_perms_list(forum_perms_get_role_raw($forumId, $role['role_id']));

        echo tpl_render('manage.forum.forumperms', compact('role', 'perms'));
        break;
}
