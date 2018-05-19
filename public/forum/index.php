<?php
use Misuzu\Database;

require_once __DIR__ . '/../../misuzu.php';

$db = Database::connection();

$categories = $db->query('
    SELECT
        f.`forum_id`, f.`forum_name`, f.`forum_type`,
        (
            SELECT COUNT(`forum_id`)
            FROM `msz_forum_categories` as sf
            WHERE sf.`forum_parent` = f.`forum_id`
        ) as `forum_children`
    FROM `msz_forum_categories` as f
    WHERE f.`forum_parent` = 0
    AND f.`forum_type` = 1
    AND f.`forum_hidden` = false
    GROUP BY f.`forum_id`
    ORDER BY f.`forum_order`
')->fetchAll();

$categories = array_merge([
    [
        'forum_id' => 0,
        'forum_name' => 'Forums',
        'forum_children' => 0,
        'forum_type' => 1,
    ],
], $categories);

$getSubCategories = $db->prepare('
    SELECT
        f.`forum_id`, f.`forum_name`, f.`forum_description`, f.`forum_type`, f.`forum_link`,
        (
            SELECT COUNT(t.`topic_id`)
            FROM `msz_forum_topics` as t
            WHERE t.`forum_id` = f.`forum_id`
        ) as `forum_topic_count`,
        (
            SELECT COUNT(p.`post_id`)
            FROM `msz_forum_posts` as p
            WHERE p.`forum_id` = f.`forum_id`
        ) as `forum_post_count`
    FROM `msz_forum_categories` as f
    WHERE f.`forum_parent` = :forum_id
    AND f.`forum_hidden` = false
    AND ((f.`forum_parent` = 0 AND f.`forum_type` != 1) OR f.`forum_parent` != 0)
    ORDER BY f.`forum_order`
');

foreach ($categories as $key => $category) {
    // replace these magic numbers with a constant later, only categories and discussion forums may have subs
    if (!in_array($category['forum_type'], [0, 1])
        && ($category['forum_id'] === 0 || $category['forum_children'] > 0)) {
        continue;
    }

    $getSubCategories->bindValue('forum_id', $category['forum_id']);
    $categories[$key]['forum_subforums'] = $getSubCategories->execute() ? $getSubCategories->fetchAll() : [];

    // one level down more!
    foreach ($categories[$key]['forum_subforums'] as $skey => $sub) {
        $getSubCategories->bindValue('forum_id', $sub['forum_id']);
        $categories[$key]['forum_subforums'][$skey]['forum_subforums']
            = $getSubCategories->execute() ? $getSubCategories->fetchAll() : [];
    }
}

$categories[0]['forum_children'] = count($categories[0]['forum_subforums']);

echo $app->getTemplating()->render('forum.index', [
    'forum_categories' => $categories,
]);
