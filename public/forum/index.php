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
        f.`forum_id`, f.`forum_name`, f.`forum_description`, f.`forum_type`, f.`forum_link`, f.`forum_link_clicks`,
        t.`topic_id` as `recent_topic_id`, p.`post_id` as `recent_post_id`,
        t.`topic_title` as `recent_topic_title`,
        p.`post_created` as `recent_post_created`,
        u.`user_id` as `recent_post_user_id`,
        u.`username` as `recent_post_username`,
        COALESCE(r.`role_colour`, CAST(0x40000000 AS UNSIGNED)) as `recent_post_user_colour`,
        (
            SELECT COUNT(`topic_id`)
            FROM `msz_forum_topics`
            WHERE `forum_id` = f.`forum_id`
        ) as `forum_topic_count`,
        (
            SELECT COUNT(`post_id`)
            FROM `msz_forum_posts`
            WHERE `forum_id` = f.`forum_id`
        ) as `forum_post_count`
    FROM `msz_forum_categories` as f
    LEFT JOIN `msz_forum_topics` as t
    ON t.`topic_id` = (
        SELECT `topic_id`
        FROM `msz_forum_topics`
        WHERE `forum_id` = f.`forum_id`
        AND `topic_deleted` IS NULL
        ORDER BY `topic_bumped` DESC
        LIMIT 1
    )
    LEFT JOIN `msz_forum_posts` as p
    ON p.`post_id` = (
        SELECT `post_id`
        FROM `msz_forum_posts`
        WHERE `topic_id` = t.`topic_id`
        ORDER BY `post_id` DESC
        LIMIT 1
    )
    LEFT JOIN `msz_users` as u
    ON u.`user_id` = p.`user_id`
    LEFT JOIN `msz_roles` as r
    ON r.`role_id` = u.`display_role`
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
