<?php
use Misuzu\Database;

require_once __DIR__ . '/../../misuzu.php';

$categories = forum_get_root_categories();

foreach ($categories as $key => $category) {
    $categories[$key]['forum_subforums'] = forum_get_children($category['forum_id'], $app->getUserId());

    foreach ($categories[$key]['forum_subforums'] as $skey => $sub) {
        if (!forum_may_have_children($sub['forum_type'])) {
            continue;
        }

        $categories[$key]['forum_subforums'][$skey]['forum_subforums']
            = forum_get_children($sub['forum_id'], $app->getUserId(), true);
    }
}

echo $app->getTemplating()->render('forum.index', [
    'forum_categories' => $categories,
]);
