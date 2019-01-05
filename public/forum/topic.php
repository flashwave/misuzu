<?php
require_once '../../misuzu.php';

$postId = (int)($_GET['p'] ?? 0);
$topicId = (int)($_GET['t'] ?? 0);

$topicUserId = user_session_current('user_id', 0);

if ($topicId < 1 && $postId > 0) {
    $postInfo = forum_post_find($postId, $topicUserId);

    if (!empty($postInfo['topic_id'])) {
        $topicId = (int)$postInfo['topic_id'];
    }
}

$topic = forum_topic_fetch($topicId, $topicUserId);
$perms = $topic
    ? forum_perms_get_user(MSZ_FORUM_PERMS_GENERAL, $topic['forum_id'], $topicUserId)
    : 0;

if (user_warning_check_restriction($topicUserId)) {
    $perms &= ~MSZ_FORUM_PERM_SET_WRITE;
}

if (!$topic || ($topic['topic_deleted'] !== null && !perms_check($perms, MSZ_FORUM_PERM_DELETE_TOPIC))) {
    echo render_error(404);
    return;
}

if (!perms_check($perms, MSZ_FORUM_PERM_VIEW_FORUM)) {
    echo render_error(403);
    return;
}

$topicPagination = pagination_create($topic['topic_post_count'], MSZ_FORUM_POSTS_PER_PAGE);

if (isset($postInfo['preceeding_post_count'])) {
    $postsPage = floor($postInfo['preceeding_post_count'] / $topicPagination['range']) + 1;
}

$postsOffset = pagination_offset($topicPagination, $postsPage ?? pagination_param('page'));

if (!pagination_is_valid_offset($postsOffset)) {
    echo render_error(404);
    return;
}

tpl_var('topic_perms', $perms);

$posts = forum_post_listing(
    $topic['topic_id'],
    $postsOffset,
    $topicPagination['range'],
    perms_check($perms, MSZ_FORUM_PERM_DELETE_ANY_POST)
);

if (!$posts) {
    echo render_error(404);
    return;
}

$canReply = empty($topic['topic_archived']) && empty($topic['topic_locked']) && empty($topic['topic_deleted'])
    && perms_check($perms, MSZ_FORUM_PERM_CREATE_POST);

forum_topic_mark_read($topicUserId, $topic['topic_id'], $topic['forum_id']);

echo tpl_render('forum.topic', [
    'topic_breadcrumbs' => forum_get_breadcrumbs($topic['forum_id']),
    'global_accent_colour' => forum_get_colour($topic['forum_id']),
    'topic_info' => $topic,
    'topic_posts' => $posts,
    'can_reply' => $canReply,
    'topic_pagination' => $topicPagination,
]);
