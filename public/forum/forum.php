<?php
use Misuzu\Database;

require_once __DIR__ . '/../../misuzu.php';

$forumId = (int)($_GET['f'] ?? 0);

if ($forumId === 0) {
    header('Location: /forum/');
    exit;
}

$db = Database::connection();
$templating = $app->getTemplating();

if ($forumId > 0) {
    $getForum = $db->prepare('
        SELECT
            `forum_id`, `forum_name`, `forum_type`, `forum_link`, `forum_parent`
        FROM `msz_forum_categories`
        WHERE `forum_id` = :forum_id
    ');
    $getForum->bindValue('forum_id', $forumId);
    $forum = $getForum->execute() ? $getForum->fetch() : [];
}

if (empty($forum) || ($forum['forum_type'] == 2 && empty($forum['forum_link']))) {
    http_response_code(404);
    echo $templating->render('errors.404');
    return;
}

if ($forum['forum_type'] == 2) {
    header('Location: ' . $forum['forum_link']);
    return;
}

// declare this, templating engine assumes it exists
$topics = [];

// no need to fetch topics for categories (or links but we're already done with those at this point)
if ($forum['forum_type'] == 0) {
    $getTopics = $db->prepare('
        SELECT
            t.`topic_id`, t.`topic_title`, t.`topic_view_count`,
            au.`user_id` as `author_id`, au.`username` as `author_name`,
            COUNT(p.`post_id`) as `topic_post_count`,
            MIN(p.`post_id`) as `topic_first_post_id`,
            MAX(p.`post_id`) as `topic_last_post_id`,
            COALESCE(ar.`role_colour`, CAST(0x40000000 AS UNSIGNED)) as `author_colour`
        FROM `msz_forum_topics` as t
        LEFT JOIN `msz_users` as au
        ON t.`user_id` = au.`user_id`
        LEFT JOIN `msz_roles` as ar
        ON ar.`role_id` = au.`display_role`
        LEFT JOIN `msz_forum_posts` as p
        ON t.`topic_id` = p.`topic_id`
        WHERE t.`forum_id` = :forum_id
        AND t.`topic_deleted` IS NULL
        GROUP BY t.`topic_id`
        ORDER BY t.`topic_type`, t.`topic_bumped`
    ');
    $getTopics->bindValue('forum_id', $forum['forum_id']);
    $topics = $getTopics->execute() ? $getTopics->fetchAll() : $topics;
}

$getSubforums = $db->prepare('
    SELECT
        `forum_id`, `forum_name`, `forum_description`, `forum_type`, `forum_link`,
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
    WHERE `forum_parent` = :forum_id
    AND `forum_hidden` = false
');
$getSubforums->bindValue('forum_id', $forum['forum_id']);
$forum['forum_subforums'] = $getSubforums->execute() ? $getSubforums->fetchAll() : [];

if (count($forum['forum_subforums']) > 0) {
    // this really, really needs a better name
    $getSubSubs = $db->prepare('
        SELECT `forum_id`, `forum_name`
        FROM `msz_forum_categories`
        WHERE `forum_parent` = :forum_id
        AND `forum_hidden` = false
    ');

    foreach ($forum['forum_subforums'] as $skey => $subforum) {
        $getSubSubs->bindValue('forum_id', $subforum['forum_id']);
        $forum['forum_subforums'][$skey]['forum_subforums'] = $getSubSubs->execute() ? $getSubSubs->fetchAll() : [];
    }
}

$lastParent = $forum['forum_parent'];
$breadcrumbs = [$forum['forum_name'] => '/forum/forum.php?f=' . $forum['forum_id']];
$getBreadcrumb = $db->prepare('
    SELECT `forum_id`, `forum_name`, `forum_parent`
    FROM `msz_forum_categories`
    WHERE `forum_id` = :forum_id
');

while ($lastParent > 0) {
    $getBreadcrumb->bindValue('forum_id', $lastParent);

    if (!$getBreadcrumb->execute()) {
        break;
    }

    $parentForum = $getBreadcrumb->fetch();

    $breadcrumbs[$parentForum['forum_name']] = '/forum/forum.php?f=' . $parentForum['forum_id'];
    $lastParent = $parentForum['forum_parent'];
}

$breadcrumbs['Forums'] = '/forum/';
$breadcrumbs = array_reverse($breadcrumbs);

echo $app->getTemplating()->render('forum.forum', [
    'forum_info' => $forum,
    'forum_breadcrumbs' => $breadcrumbs,
    'forum_topics' => $topics,
]);
