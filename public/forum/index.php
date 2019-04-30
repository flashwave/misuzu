<?php
require_once '../../misuzu.php';

$indexMode = !empty($_GET['m']) && is_string($_GET['m']) ? (string)$_GET['m'] : '';
$forumId = !empty($_GET['f']) && is_string($_GET['f']) ? (int)$_GET['f'] : 0;

switch ($indexMode) {
    case 'mark':
        $markEntireForum = $forumId === 0;

        if (user_session_active() && csrf_verify('forum_mark', $_GET['c'] ?? '')) {
            forum_mark_read($markEntireForum ? null : $forumId, user_session_current('user_id', 0));
        }

        header('Location: ' . url($markEntireForum ? 'forum-index' : 'forum-category', ['forum' => $forumId]));
        break;

    default:
        $categories = forum_get_root_categories(user_session_current('user_id', 0));
        $blankForum = count($categories) <= 1 && $categories[0]['forum_children'] < 1;

        foreach ($categories as $key => $category) {
            $categories[$key]['forum_subforums'] = forum_get_children(
                $category['forum_id'],
                user_session_current('user_id', 0)
            );

            foreach ($categories[$key]['forum_subforums'] as $skey => $sub) {
                if (!forum_may_have_children($sub['forum_type'])) {
                    continue;
                }

                $categories[$key]['forum_subforums'][$skey]['forum_subforums']
                    = forum_get_children(
                        $sub['forum_id'],
                        user_session_current('user_id', 0)
                    );
            }
        }

        echo tpl_render('forum.index', [
            'forum_categories' => $categories,
            'forum_empty' => $blankForum,
        ]);
        break;
}
