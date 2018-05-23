<?php
use Misuzu\Database;

require_once __DIR__ . '/../../misuzu.php';

$forumId = max((int)($_GET['f'] ?? 0), 0);
$topicsOffset = max((int)($_GET['o'] ?? 0), 0);
$topicsRange = max(min((int)($_GET['r'] ?? 20), 50), 10);

if ($forumId === 0) {
    header('Location: /forum/');
    exit;
}

$db = Database::connection();
$templating = $app->getTemplating();

$getForum = $db->prepare('
    SELECT
        `forum_id`, `forum_name`, `forum_type`, `forum_link`, `forum_link_clicks`, `forum_parent`,
        (
            SELECT COUNT(`topic_id`)
            FROM `msz_forum_topics`
            WHERE `forum_id` = f.`forum_id`
        ) as `forum_topic_count`
    FROM `msz_forum_categories` as f
    WHERE `forum_id` = :forum_id
');
$getForum->bindValue('forum_id', $forumId);
$forum = $getForum->execute() ? $getForum->fetch() : [];

if (empty($forum) || ($forum['forum_type'] == MSZ_FORUM_TYPE_LINK && empty($forum['forum_link']))) {
    http_response_code(404);
    echo $templating->render('errors.404');
    return;
}

if ($forum['forum_type'] == MSZ_FORUM_TYPE_LINK) {
    forum_increment_clicks($forum['forum_id']);
    header('Location: ' . $forum['forum_link']);
    return;
}

// declare this, templating engine assumes it exists
$topics = [];

// no need to fetch topics for categories (or links but we're already done with those at this point)
if ($forum['forum_type'] == MSZ_FORUM_TYPE_DISCUSSION) {
    $getTopics = $db->prepare('
        SELECT
            t.`topic_id`, t.`topic_title`, t.`topic_view_count`, t.`topic_locked`, t.`topic_type`, t.`topic_created`,
            au.`user_id` as `author_id`, au.`username` as `author_name`,
            COALESCE(ar.`role_colour`, CAST(0x40000000 AS UNSIGNED)) as `author_colour`,
            lp.`post_id` as `response_id`,
            lp.`post_created` as `response_created`,
            lu.`user_id` as `respondent_id`,
            lu.`username` as `respondent_name`,
            COALESCE(lr.`role_colour`, CAST(0x40000000 AS UNSIGNED)) as `respondent_colour`,
            (
                SELECT COUNT(`post_id`)
                FROM `msz_forum_posts`
                WHERE `topic_id` = t.`topic_id`
            ) as `topic_post_count`
        FROM `msz_forum_topics` as t
        LEFT JOIN `msz_users` as au
        ON t.`user_id` = au.`user_id`
        LEFT JOIN `msz_roles` as ar
        ON ar.`role_id` = au.`display_role`
        LEFT JOIN `msz_forum_posts` as lp
        ON lp.`post_id` = (
            SELECT `post_id`
            FROM `msz_forum_posts`
            WHERE `topic_id` = t.`topic_id`
            ORDER BY `post_id` DESC
            LIMIT 1
        )
        LEFT JOIN `msz_users` as lu
        ON lu.`user_id` = lp.`user_id`
        LEFT JOIN `msz_roles` as lr
        ON lr.`role_id` = lu.`display_role`
        WHERE t.`forum_id` = :forum_id
        AND t.`topic_deleted` IS NULL
        ORDER BY t.`topic_type` DESC, t.`topic_bumped` DESC
        LIMIT :offset, :take
    ');
    $getTopics->bindValue('forum_id', $forum['forum_id']);
    $getTopics->bindValue('offset', $topicsOffset);
    $getTopics->bindValue('take', $topicsRange);
    $topics = $getTopics->execute() ? $getTopics->fetchAll() : $topics;
}

$getSubforums = $db->prepare('
    SELECT
        f.`forum_id`, f.`forum_name`, f.`forum_description`, f.`forum_type`, f.`forum_link`, f.`forum_archived`,
        t.`topic_id` as `recent_topic_id`, p.`post_id` as `recent_post_id`,
        t.`topic_title` as `recent_topic_title`,
        p.`post_created` as `recent_post_created`,
        u.`user_id` as `recent_post_user_id`,
        u.`username` as `recent_post_username`,
        COALESCE(r.`role_colour`, CAST(0x40000000 AS UNSIGNED)) as `recent_post_user_colour`,
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

echo $app->getTemplating()->render('forum.forum', [
    'forum_breadcrumbs' => forum_get_breadcrumbs($forum['forum_id']),
    'forum_info' => $forum,
    'forum_topics' => $topics,
    'forum_offset' => $topicsOffset,
    'forum_range' => $topicsRange,
]);
