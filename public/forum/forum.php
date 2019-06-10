<?php
require_once '../../misuzu.php';

$forumId = !empty($_GET['f']) && is_string($_GET['f']) ? (int)$_GET['f'] : 0;
$forumId = max($forumId, 0);

if($forumId === 0) {
    url_redirect('forum-index');
    exit;
}

$forum = forum_get($forumId);
$forumUserId = user_session_current('user_id', 0);

if(empty($forum) || ($forum['forum_type'] == MSZ_FORUM_TYPE_LINK && empty($forum['forum_link']))) {
    echo render_error(404);
    return;
}

$perms = forum_perms_get_user($forum['forum_id'], $forumUserId)[MSZ_FORUM_PERMS_GENERAL];

if(!perms_check($perms, MSZ_FORUM_PERM_VIEW_FORUM)) {
    echo render_error(403);
    return;
}

if(user_warning_check_restriction($forumUserId)) {
    $perms &= ~MSZ_FORUM_PERM_SET_WRITE;
}

tpl_var('forum_perms', $perms);

if($forum['forum_type'] == MSZ_FORUM_TYPE_LINK) {
    forum_increment_clicks($forum['forum_id']);
    redirect($forum['forum_link']);
    return;
}

$forumPagination = pagination_create($forum['forum_topic_count'], 20);
$topicsOffset = pagination_offset($forumPagination, pagination_param());

if(!pagination_is_valid_offset($topicsOffset) && $forum['forum_topic_count'] > 0) {
    echo render_error(404);
    return;
}

$forumMayHaveTopics = forum_may_have_topics($forum['forum_type']);
$topics = $forumMayHaveTopics
    ? forum_topic_listing(
        $forum['forum_id'],
        $forumUserId,
        $topicsOffset,
        $forumPagination['range'],
        perms_check($perms, MSZ_FORUM_PERM_DELETE_ANY_POST),
        forum_has_priority_voting($forum['forum_type'])
    )
    : [];

$forumMayHaveChildren = forum_may_have_children($forum['forum_type']);

if($forumMayHaveChildren) {
    $forum['forum_subforums'] = forum_get_children($forum['forum_id'], $forumUserId);

    foreach($forum['forum_subforums'] as $skey => $subforum) {
        $forum['forum_subforums'][$skey]['forum_subforums']
            = forum_get_children($subforum['forum_id'], $forumUserId);
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
