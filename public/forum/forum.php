<?php
require_once __DIR__ . '/../../misuzu.php';

$forumId = max((int)($_GET['f'] ?? 0), 0);
$topicsOffset = max((int)($_GET['o'] ?? 0), 0);
$topicsRange = max(min((int)($_GET['r'] ?? 20), 50), 10);

if ($forumId === 0) {
    header('Location: /forum/');
    exit;
}

$templating = $app->getTemplating();
$forum = forum_fetch($forumId);

if (empty($forum) || ($forum['forum_type'] == MSZ_FORUM_TYPE_LINK && empty($forum['forum_link']))) {
    echo render_error(404);
    return;
}

if ($forum['forum_type'] == MSZ_FORUM_TYPE_LINK) {
    forum_increment_clicks($forum['forum_id']);
    header('Location: ' . $forum['forum_link']);
    return;
}

$topics = forum_may_have_topics($forum['forum_type'])
    ? forum_topic_listing($forum['forum_id'], $app->getUserId(), $topicsOffset, $topicsRange)
    : [];

$forum['forum_subforums'] = forum_get_children($forum['forum_id'], $app->getUserId());

foreach ($forum['forum_subforums'] as $skey => $subforum) {
    $forum['forum_subforums'][$skey]['forum_subforums']
        = forum_get_children($subforum['forum_id'], $app->getUserId(), true);
}

echo $app->getTemplating()->render('forum.forum', [
    'forum_breadcrumbs' => forum_get_breadcrumbs($forum['forum_id']),
    'forum_info' => $forum,
    'forum_topics' => $topics,
    'forum_offset' => $topicsOffset,
    'forum_range' => $topicsRange,
]);
