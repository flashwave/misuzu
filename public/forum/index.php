<?php
require_once '../../misuzu.php';

switch ($_GET['m'] ?? '') {
    case 'mark':
        $forumId = (int)($_GET['f'] ?? null);
        $markEntireForum = $forumId === 0;
        $markAction = forum_mark_read($markEntireForum ? null : $forumId, user_session_current('user_id', 0));
        header('Location: /forum' . (!$markAction || $markEntireForum ? '' : url_construct('/forum.php', ['f' => $forumId])));
        break;

    case 'new':
        break;

    case 'your':
        break;

    default:
        $categories = forum_get_root_categories(user_session_current('user_id', 0));
        $blankForum = count($categories) <= 1 && $categories[0]['forum_children'] < 1;

        foreach ($categories as $key => $category) {
            $categories[$key]['forum_subforums'] = forum_get_children(
                $category['forum_id'],
                user_session_current('user_id', 0),
                perms_check($category['forum_permissions'], MSZ_FORUM_PERM_DELETE_TOPIC | MSZ_FORUM_PERM_DELETE_ANY_POST)
            );

            foreach ($categories[$key]['forum_subforums'] as $skey => $sub) {
                if (!forum_may_have_children($sub['forum_type'])) {
                    continue;
                }

                $categories[$key]['forum_subforums'][$skey]['forum_subforums']
                    = forum_get_children(
                        $sub['forum_id'],
                        user_session_current('user_id', 0),
                        perms_check($sub['forum_permissions'], MSZ_FORUM_PERM_DELETE_TOPIC | MSZ_FORUM_PERM_DELETE_ANY_POST),
                        true
                    );
            }
        }

        echo tpl_render('forum.index', [
            'forum_categories' => $categories,
            'forum_empty' => $blankForum,
        ]);
        break;
}
