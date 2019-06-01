<?php
require_once '../../misuzu.php';

$postId = !empty($_GET['p']) && is_string($_GET['p']) ? (int)$_GET['p'] : 0;
$topicId = !empty($_GET['t']) && is_string($_GET['t']) ? (int)$_GET['t'] : 0;
$moderationMode = !empty($_GET['m']) && is_string($_GET['m']) ? (string)$_GET['m'] : '';
$submissionConfirmed = !empty($_GET['confirm']) && is_string($_GET['confirm']) && $_GET['confirm'] === '1';

$topicUserId = user_session_current('user_id', 0);

if ($topicId < 1 && $postId > 0) {
    $postInfo = forum_post_find($postId, $topicUserId);

    if (!empty($postInfo['topic_id'])) {
        $topicId = (int)$postInfo['topic_id'];
    }
}

$topic = forum_topic_get($topicId, true);
$perms = $topic
    ? forum_perms_get_user($topic['forum_id'], $topicUserId)[MSZ_FORUM_PERMS_GENERAL]
    : 0;

if (user_warning_check_restriction($topicUserId)) {
    $perms &= ~MSZ_FORUM_PERM_SET_WRITE;
}

$topicIsDeleted = !empty($topic['topic_deleted']);
$canDeleteAny = perms_check($perms, MSZ_FORUM_PERM_DELETE_ANY_POST);

if (!$topic || ($topicIsDeleted && !$canDeleteAny)) {
    echo render_error(404);
    return;
}

if (!perms_check($perms, MSZ_FORUM_PERM_VIEW_FORUM)) {
    echo render_error(403);
    return;
}

if (!empty($topic['poll_id'])) {
    $pollOptions = forum_poll_get_options($topic['poll_id']);
    $pollUserAnswers = forum_poll_get_user_answers($topic['poll_id'], $topicUserId);
}

if (forum_has_priority_voting($topic['forum_type'])) {
    $topicPriority = forum_topic_priority($topic['topic_id']);
}

$topicIsLocked = !empty($topic['topic_locked']);
$topicIsArchived = !empty($topic['topic_archived']);
$topicPostsTotal = (int)($topic['topic_count_posts'] + $topic['topic_count_posts_deleted']);
$topicIsFrozen = $topicIsArchived || $topicIsDeleted;
$canDeleteOwn = !$topicIsFrozen && !$topicIsLocked && perms_check($perms, MSZ_FORUM_PERM_DELETE_POST);
$canBumpTopic = !$topicIsFrozen && perms_check($perms, MSZ_FORUM_PERM_BUMP_TOPIC);
$canLockTopic = !$topicIsFrozen && perms_check($perms, MSZ_FORUM_PERM_LOCK_TOPIC);
$canNukeOrRestore = $canDeleteAny && $topicIsDeleted;
$canDelete = !$topicIsDeleted && (
    $canDeleteAny || (
        $topicPostsTotal > 0
        && $topicPostsTotal <= MSZ_FORUM_TOPIC_DELETE_POST_LIMIT
        && $canDeleteOwn
        && $topic['author_user_id'] === $topicUserId
    )
);

$validModerationModes = [
    'delete', 'restore', 'nuke',
    'bump', 'lock', 'unlock',
];

if (in_array($moderationMode, $validModerationModes, true)) {
    $redirect = !empty($_SERVER['HTTP_REFERER']) && empty($_SERVER['HTTP_X_MISUZU_XHR']) ? $_SERVER['HTTP_REFERER'] : '';
    $isXHR = !$redirect;

    if ($isXHR) {
        header('Content-Type: application/json; charset=utf-8');
    } elseif (!is_local_url($redirect)) {
        echo render_info('Possible request forgery detected.', 403);
        return;
    }

    if (!csrf_verify('forum_post', $_GET['csrf'] ?? '') && !csrf_verify('forum_post', csrf_http_header_parse($_SERVER['HTTP_X_MISUZU_CSRF'] ?? '')['token'])) {
        echo render_info_or_json($isXHR, "Couldn't verify this request, please refresh the page and try again.", 403);
        return;
    }

    header(csrf_http_header('forum_post'));

    if (!user_session_active()) {
        echo render_info_or_json($isXHR, 'You must be logged in to manage posts.', 401);
        return;
    }

    if (user_warning_check_expiration($topicUserId, MSZ_WARN_BAN) > 0) {
        echo render_info_or_json($isXHR, 'You have been banned, check your profile for more information.', 403);
        return;
    }
    if (user_warning_check_expiration($topicUserId, MSZ_WARN_SILENCE) > 0) {
        echo render_info_or_json($isXHR, 'You have been silenced, check your profile for more information.', 403);
        return;
    }

    switch ($moderationMode) {
        case 'delete':
            $canDeleteCode = forum_topic_can_delete($topic, $topicUserId);
            $canDeleteMsg = '';
            $responseCode = 200;

            switch ($canDeleteCode) {
                case MSZ_E_FORUM_TOPIC_DELETE_USER:
                    $responseCode = 401;
                    $canDeleteMsg = 'You must be logged in to delete topics.';
                    break;
                case MSZ_E_FORUM_TOPIC_DELETE_TOPIC:
                    $responseCode = 404;
                    $canDeleteMsg = "This topic doesn't exist.";
                    break;
                case MSZ_E_FORUM_TOPIC_DELETE_DELETED:
                    $responseCode = 404;
                    $canDeleteMsg = 'This topic has already been marked as deleted.';
                    break;
                case MSZ_E_FORUM_TOPIC_DELETE_OWNER:
                    $responseCode = 403;
                    $canDeleteMsg = 'You can only delete your own topics.';
                    break;
                case MSZ_E_FORUM_TOPIC_DELETE_OLD:
                    $responseCode = 401;
                    $canDeleteMsg = 'This topic has existed for too long. Ask a moderator to remove if it absolutely necessary.';
                    break;
                case MSZ_E_FORUM_TOPIC_DELETE_PERM:
                    $responseCode = 401;
                    $canDeleteMsg = 'You are not allowed to delete topics.';
                    break;
                case MSZ_E_FORUM_TOPIC_DELETE_POSTS:
                    $responseCode = 403;
                    $canDeleteMsg = 'This topic already has replies, you may no longer delete it. Ask a moderator to remove if it absolutely necessary.';
                    break;
                case MSZ_E_FORUM_TOPIC_DELETE_OK:
                    break;
                default:
                    $responseCode = 500;
                    $canDeleteMsg = sprintf('Unknown error \'%d\'', $canDelete);
            }

            if ($canDeleteCode !== MSZ_E_FORUM_TOPIC_DELETE_OK) {
                if ($isXHR) {
                    http_response_code($responseCode);
                    echo json_encode([
                        'success' => false,
                        'topic_id' => $topic['topic_id'],
                        'code' => $canDeleteCode,
                        'message' => $canDeleteMsg,
                    ]);
                    break;
                }

                echo render_info($canDeleteMsg, $responseCode);
                break;
            }

            if (!$isXHR) {
                if (!isset($_GET['confirm'])) {
                    echo tpl_render('forum.confirm', [
                        'title' => 'Confirm topic deletion',
                        'class' => 'far fa-trash-alt',
                        'message' => sprintf('You are about to delete topic #%d. Are you sure about that?', $topic['topic_id']),
                        'params' => [
                            't' => $topic['topic_id'],
                            'm' => 'delete',
                        ],
                    ]);
                    break;
                } elseif (!$submissionConfirmed) {
                    header("Location: " . url(
                        'forum-topic',
                        ['topic' => $topic['topic_id']]
                    ));
                    break;
                }
            }

            $deleteTopic = forum_topic_delete($topic['topic_id']);

            if ($deleteTopic) {
                audit_log(MSZ_AUDIT_FORUM_TOPIC_DELETE, $topicUserId, [$topic['topic_id']]);
            }

            if ($isXHR) {
                echo json_encode([
                    'success' => $deleteTopic,
                    'topic_id' => $topic['topic_id'],
                    'message' => $deleteTopic ? 'Topic deleted!' : 'Failed to delete topic.',
                ]);
                break;
            }

            if (!$deleteTopic) {
                echo render_error(500);
                break;
            }

            header('Location: ' . url('forum-category', [
                'forum' => $topic['forum_id'],
            ]));
            break;

        case 'restore':
            if (!$canNukeOrRestore) {
                echo render_error(403);
                break;
            }

            if (!$isXHR) {
                if (!isset($_GET['confirm'])) {
                    echo tpl_render('forum.confirm', [
                        'title' => 'Confirm topic restore',
                        'class' => 'fas fa-magic',
                        'message' => sprintf('You are about to restore topic #%d. Are you sure about that?', $topic['topic_id']),
                        'params' => [
                            't' => $topic['topic_id'],
                            'm' => 'restore',
                        ],
                    ]);
                    break;
                } elseif (!$submissionConfirmed) {
                    header("Location: " . url('forum-topic', [
                        'topic' => $topic['topic_id'],
                    ]));
                    break;
                }
            }

            $restoreTopic = forum_topic_restore($topic['topic_id']);

            if (!$restoreTopic) {
                echo render_error(500);
                break;
            }

            audit_log(MSZ_AUDIT_FORUM_TOPIC_RESTORE, $topicUserId, [$topic['topic_id']]);
            http_response_code(204);

            if (!$isXHR) {
                header('Location: ' . url('forum-category', [
                    'forum' => $topic['forum_id'],
                ]));
            }
            break;

        case 'nuke':
            if (!$canNukeOrRestore) {
                echo render_error(403);
                break;
            }

            if (!$isXHR) {
                if (!isset($_GET['confirm'])) {
                    echo tpl_render('forum.confirm', [
                        'title' => 'Confirm topic nuke',
                        'class' => 'fas fa-radiation',
                        'message' => sprintf('You are about to PERMANENTLY DELETE topic #%d. Are you sure about that?', $topic['topic_id']),
                        'params' => [
                            't' => $topic['topic_id'],
                            'm' => 'nuke',
                        ],
                    ]);
                    break;
                } elseif (!$submissionConfirmed) {
                    header('Location: ' . url('forum-topic', [
                        'topic' => $topic['topic_id'],
                    ]));
                    break;
                }
            }

            $nukeTopic = forum_topic_nuke($topic['topic_id']);

            if (!$nukeTopic) {
                echo render_error(500);
                break;
            }

            audit_log(MSZ_AUDIT_FORUM_TOPIC_NUKE, $topicUserId, [$topic['topic_id']]);
            http_response_code(204);

            if (!$isXHR) {
                header('Location: ' . url('forum-category', [
                    'forum' => $topic['forum_id'],
                ]));
            }
            break;

        case 'bump':
            if ($canBumpTopic && forum_topic_bump($topic['topic_id'])) {
                audit_log(MSZ_AUDIT_FORUM_TOPIC_BUMP, $topicUserId, [$topic['topic_id']]);
            }

            header('Location: ' . url('forum-topic', [
                'topic' => $topic['topic_id'],
            ]));
            break;

        case 'lock':
            if ($canLockTopic && !$topicIsLocked && forum_topic_lock($topic['topic_id'])) {
                audit_log(MSZ_AUDIT_FORUM_TOPIC_LOCK, $topicUserId, [$topic['topic_id']]);
            }

            header('Location: ' . url('forum-topic', [
                'topic' => $topic['topic_id'],
            ]));
            break;

        case 'unlock':
            if ($canLockTopic && $topicIsLocked && forum_topic_unlock($topic['topic_id'])) {
                audit_log(MSZ_AUDIT_FORUM_TOPIC_UNLOCK, $topicUserId, [$topic['topic_id']]);
            }

            header('Location: ' . url('forum-topic', [
                'topic' => $topic['topic_id'],
            ]));
            break;
    }
    return;
}

$topicPosts = $topic['topic_count_posts'];

if ($canDeleteAny) {
    $topicPosts += $topic['topic_count_posts_deleted'];
}

$topicPagination = pagination_create($topicPosts, MSZ_FORUM_POSTS_PER_PAGE);

if (isset($postInfo['preceeding_post_count'])) {
    $preceedingPosts = $postInfo['preceeding_post_count'];

    if ($canDeleteAny) {
        $preceedingPosts += $postInfo['preceeding_post_deleted_count'];
    }

    $postsPage = floor($preceedingPosts / $topicPagination['range']) + 1;
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

$canReply = !$topicIsArchived && !$topicIsLocked && !$topicIsDeleted && perms_check($perms, MSZ_FORUM_PERM_CREATE_POST);

forum_topic_mark_read($topicUserId, $topic['topic_id'], $topic['forum_id']);

echo tpl_render('forum.topic', [
    'topic_breadcrumbs' => forum_get_breadcrumbs($topic['forum_id']),
    'global_accent_colour' => forum_get_colour($topic['forum_id']),
    'topic_info' => $topic,
    'topic_posts' => $posts,
    'can_reply' => $canReply,
    'topic_pagination' => $topicPagination,
    'topic_can_delete' => $canDelete,
    'topic_can_nuke_or_restore' => $canNukeOrRestore,
    'topic_can_bump' => $canBumpTopic,
    'topic_can_lock' => $canLockTopic,
    'topic_poll_options' => $pollOptions ?? [],
    'topic_poll_user_answers' => $pollUserAnswers ?? [],
    'topic_priority_votes' => $topicPriority ?? [],
]);
