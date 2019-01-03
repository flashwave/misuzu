<?php
require_once '../../misuzu.php';

$forumId = max((int)($_GET['f'] ?? 0), 0);

if ($forumId === 0) {
    header('Location: /forum/');
    exit;
}

$forum = forum_fetch($forumId);

if (empty($forum) || ($forum['forum_type'] == MSZ_FORUM_TYPE_LINK && empty($forum['forum_link']))) {
    echo render_error(404);
    return;
}

$perms = forum_perms_get_user(MSZ_FORUM_PERMS_GENERAL, $forum['forum_id'], user_session_current('user_id', 0));

if (!perms_check($perms, MSZ_FORUM_PERM_VIEW_FORUM)) {
    echo render_error(403);
    return;
}

if (user_warning_check_restriction(user_session_current('user_id', 0))) {
    $perms &= ~MSZ_FORUM_PERM_SET_WRITE;
}

tpl_var('forum_perms', $perms);

if ($forum['forum_type'] == MSZ_FORUM_TYPE_LINK) {
    forum_increment_clicks($forum['forum_id']);
    header('Location: ' . $forum['forum_link']);
    return;
}

$forumPagination = pagination_create($forum['forum_topic_count'], 20);
$topicsOffset = pagination_offset($forumPagination, pagination_param());

if (!pagination_is_valid_offset($topicsOffset)) {
    echo render_error(404);
    return;
}

$forumMayHaveTopics = forum_may_have_topics($forum['forum_type']);
$topics = $forumMayHaveTopics
    ? forum_topic_listing(
        $forum['forum_id'],
        user_session_current('user_id', 0),
        $topicsOffset,
        $forumPagination['range'],
        perms_check($perms, MSZ_FORUM_PERM_DELETE_TOPIC | MSZ_FORUM_PERM_DELETE_ANY_POST)
    )
    : [];

$forumMayHaveChildren = forum_may_have_children($forum['forum_type']);

if ($forumMayHaveChildren) {
    $forum['forum_subforums'] = forum_get_children($forum['forum_id'], user_session_current('user_id', 0));

    foreach ($forum['forum_subforums'] as $skey => $subforum) {
        $forum['forum_subforums'][$skey]['forum_subforums']
            = forum_get_children($subforum['forum_id'], user_session_current('user_id', 0), true);
    }
}

echo tpl_render('forum.forum', [
    'forum_breadcrumbs' => forum_get_breadcrumbs($forum['forum_id']),
    'global_accent_colour' => forum_get_colour($forum['forum_id']),
    'forum_may_have_topics' => $forumMayHaveTopics,
    'forum_may_have_children' => $forumMayHaveChildren,
    'forum_info' => $forum,
    'forum_topics' => $topics,
    'forum_pagination' => $forumPagination,
]);
