<?php
require_once __DIR__ . '/../../misuzu.php';

$postId = (int)($_GET['p'] ?? 0);
$topicId = (int)($_GET['t'] ?? 0);
$postsOffset = max((int)($_GET['o'] ?? 0), 0);
$postsRange = max(min((int)($_GET['r'] ?? 10), 25), 5);

if ($topicId < 1 && $postId > 0) {
    $postInfo = forum_post_find($postId);

    if ($postInfo) {
        $topicId = (int)$postInfo['target_topic_id'];
        $postsOffset = floor($postInfo['preceeding_post_count'] / $postsRange) * $postsRange;
    }
}

$topic = forum_topic_fetch($topicId);

if (!$topic) {
    echo render_error(404);
    return;
}

$posts = forum_post_listing($topic['topic_id'], $postsOffset, $postsRange);

if (!$posts) {
    echo render_error(404);
    return;
}

forum_topic_mark_read($app->getUserId(), $topic['topic_id'], $topic['forum_id']);

echo tpl_render('forum.topic', [
    'topic_breadcrumbs' => forum_get_breadcrumbs($topic['forum_id']),
    'topic_info' => $topic,
    'topic_posts' => $posts,
    'topic_offset' => $postsOffset,
    'topic_range' => $postsRange,
]);
