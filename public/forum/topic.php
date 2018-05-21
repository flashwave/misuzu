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
    $getTopicId = $db->prepare('
        SELECT `topic_id`
        FROM `msz_forum_posts`
        WHERE `post_id` = :post_id
    ');
    $getTopicId->bindValue('post_id', $postId);
    $topicId = $getTopicId->execute() ? (int)$getTopicId->fetchColumn() : 0;
}

$getTopic = $db->prepare('
    SELECT
        t.`topic_id`, t.`forum_id`, t.`topic_title`, t.`topic_type`, t.`topic_status`,
        (
            SELECT MIN(p.`post_id`)
            FROM `msz_forum_posts` as p
            WHERE p.`topic_id` = t.`topic_id`
        ) as `topic_first_post_id`
    FROM `msz_forum_topics` as t
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
        `post_id`, `post_text`, `post_created`,
        `topic_id`
    FROM `msz_forum_posts`
    WHERE `topic_id` = :topic_id
    AND `post_deleted` IS NULL
');
$getPosts->bindValue('topic_id', $topic['topic_id']);
$posts = $getPosts->execute() ? $getPosts->fetchAll() : [];

$lastParent = $topic['forum_id'];
$breadcrumbs = [];
$getBreadcrumb = $db->prepare('
    SELECT `forum_id`, `forum_name`, `forum_parent`
    FROM `msz_forum_categories`
    WHERE `forum_id` = :forum_id
');

while ($lastParent > 0) {
    $getBreadcrumb->bindValue('forum_id', $lastParent);
    $breadcrumb = $getBreadcrumb->execute() ? $getBreadcrumb->fetch() : [];

    if (!$breadcrumb) {
        break;
    }

    $breadcrumbs[$breadcrumb['forum_name']] = '/forum/forum.php?f=' . $breadcrumb['forum_id'];
    $lastParent = $breadcrumb['forum_parent'];
}

$breadcrumbs['Forums'] = '/forum/';
$breadcrumbs = array_reverse($breadcrumbs);

echo $templating->render('forum.topic', [
    'topic_breadcrumbs' => $breadcrumbs,
    'topic_info' => $topic,
    'topic_posts' => $posts,
]);
