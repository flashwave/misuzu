<?php
use Misuzu\Database;

require_once __DIR__ . '/../../misuzu.php';

$db = Database::connection();
$templating = $app->getTemplating();

$postId = (int)($_GET['p'] ?? 0);
$topicId = (int)($_GET['t'] ?? 0);
$postsOffset = max((int)($_GET['o'] ?? 0), 0);
$postsRange = max(min((int)($_GET['r'] ?? 10), 25), 5);

// find topic id
if ($topicId < 1 && $postId > 0) {
    $postInfo = forum_post_find($postId);

    if ($postInfo) {
        $topicId = (int)$postInfo['target_topic_id'];
        $postsOffset = floor($postInfo['preceeding_post_count'] / $postsRange) * $postsRange;
    }
}

$getTopic = $db->prepare('
    SELECT
        t.`topic_id`, t.`forum_id`, t.`topic_title`, t.`topic_type`, t.`topic_locked`,
        f.`forum_archived` as `topic_archived`,
        (
            SELECT MIN(`post_id`)
            FROM `msz_forum_posts`
            WHERE `topic_id` = t.`topic_id`
        ) as `topic_first_post_id`,
        (
            SELECT COUNT(`post_id`)
            FROM `msz_forum_posts`
            WHERE `topic_id` = t.`topic_id`
        ) as `topic_post_count`
    FROM `msz_forum_topics` as t
    LEFT JOIN `msz_forum_categories` as f
    ON f.`forum_id` = t.`forum_id`
    WHERE t.`topic_id` = :topic_id
    AND t.`topic_deleted` IS NULL
');
$getTopic->bindValue('topic_id', $topicId);
$topic = $getTopic->execute() ? $getTopic->fetch() : false;

if (!$topic) {
    http_response_code(404);
    echo $templating->render('errors.404');
    return;
}

$getPosts = $db->prepare('
    SELECT
        p.`post_id`, p.`post_text`, p.`post_created`,
        p.`topic_id`,
        u.`user_id` as `poster_id`,
        u.`username` as `poster_name`,
        u.`created_at` as `poster_joined`,
        COALESCE(r.`role_colour`, CAST(0x40000000 AS UNSIGNED)) as `poster_colour`
    FROM `msz_forum_posts` as p
    LEFT JOIN `msz_users` as u
    ON u.`user_id` = p.`user_id`
    LEFT JOIN `msz_roles` as r
    ON r.`role_id` = u.`display_role`
    WHERE `topic_id` = :topic_id
    AND `post_deleted` IS NULL
    ORDER BY `post_id`
    LIMIT :offset, :take
');
$getPosts->bindValue('topic_id', $topic['topic_id']);
$getPosts->bindValue('offset', $postsOffset);
$getPosts->bindValue('take', $postsRange);
$posts = $getPosts->execute() ? $getPosts->fetchAll() : [];

if (!$posts) {
    http_response_code(404);
    echo $templating->render('errors.404');
    return;
}

echo $templating->render('forum.topic', [
    'topic_breadcrumbs' => forum_get_breadcrumbs($topic['forum_id']),
    'topic_info' => $topic,
    'topic_posts' => $posts,
    'topic_offset' => $postsOffset,
    'topic_range' => $postsRange,
]);
