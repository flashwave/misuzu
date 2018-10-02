<?php
require_once __DIR__ . '/../../misuzu.php';

$categories = forum_get_root_categories(user_session_current('user_id', 0));
$blankForum = count($categories) <= 1 && $categories[0]['forum_children'] < 1;

foreach ($categories as $key => $category) {
    $categories[$key]['forum_subforums'] = forum_get_children($category['forum_id'], user_session_current('user_id', 0));

    foreach ($categories[$key]['forum_subforums'] as $skey => $sub) {
        if (!forum_may_have_children($sub['forum_type'])) {
            continue;
        }

        $categories[$key]['forum_subforums'][$skey]['forum_subforums']
            = forum_get_children($sub['forum_id'], user_session_current('user_id', 0), true);
    }
}

echo tpl_render('forum.index', [
    'forum_categories' => $categories,
    'forum_empty' => $blankForum,
]);
