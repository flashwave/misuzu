<?php
namespace Misuzu;

use Misuzu\Users\User;
use Misuzu\Users\UserNotFoundException;

require_once '../../misuzu.php';

if(!MSZ_DEBUG)
    return;

$topicId = !empty($_GET['t']) && is_string($_GET['t']) ? (int)$_GET['t'] : 0;
$topicUser = User::getCurrent();
$topicUserId = $topicUser === null ? 0 : $topicUser->getId();

if($topicUserId < 1) {
    echo render_error(403);
    return;
}

$topic = forum_topic_get($topicId, true);
$perms = $topic
    ? forum_perms_get_user($topic['forum_id'], $topicUserId)[MSZ_FORUM_PERMS_GENERAL]
    : 0;

if(isset($topicUser) && $topicUser->hasActiveWarning())
    $perms &= ~MSZ_FORUM_PERM_SET_WRITE;

$topicIsDeleted = !empty($topic['topic_deleted']);
$canDeleteAny = perms_check($perms, MSZ_FORUM_PERM_DELETE_ANY_POST);

if(!$topic || ($topicIsDeleted && !$canDeleteAny)) {
    echo render_error(404);
    return;
}

if(!perms_check($perms, MSZ_FORUM_PERM_VIEW_FORUM, true) // | MSZ_FORUM_PERM_PRIORITY_VOTE
    || !$canDeleteAny
    && (
        !empty($topic['topic_locked'])
        || !empty($topic['topic_archived'])
    )
) {
    echo render_error(403);
    return;
}

if(!forum_has_priority_voting($topic['forum_type'])) {
    echo render_error(400);
    return;
}

forum_topic_priority_increase($topicId, $topicUserId);

url_redirect('forum-topic', ['topic' => $topicId]);
