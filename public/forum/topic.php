<?php
namespace Misuzu;

use Misuzu\AuditLog;
use Misuzu\Forum\ForumTopic;
use Misuzu\Forum\ForumTopicNotFoundException;
use Misuzu\Forum\ForumPost;
use Misuzu\Forum\ForumPostNotFoundException;
use Misuzu\Users\User;
use Misuzu\Users\UserSession;

require_once '../../misuzu.php';

$postId              =    (int)filter_input(INPUT_GET, 'p', FILTER_SANITIZE_NUMBER_INT);
$topicId             =    (int)filter_input(INPUT_GET, 't', FILTER_SANITIZE_NUMBER_INT);
$moderationMode      = (string)filter_input(INPUT_GET, 'm', FILTER_SANITIZE_STRING);
$submissionConfirmed =         filter_input(INPUT_GET, 'confirm') === '1';

$topicUser = User::getCurrent();
$topicUserId = $topicUser === null ? 0 : $topicUser->getId();

if($topicId < 1 && $postId > 0) {
    $postInfo = forum_post_find($postId, $topicUserId);

    if(!empty($postInfo['topic_id']))
        $topicId = (int)$postInfo['topic_id'];
}

try {
    $topicInfo = ForumTopic::byId($topicId);
} catch(ForumTopicNotFoundException $ex) {
    echo render_error(404);
    return;
}

$perms = forum_perms_get_user($topicInfo->getCategory()->getId(), $topicUserId)[MSZ_FORUM_PERMS_GENERAL];

if(isset($topicUser) && $topicUser->hasActiveWarning())
    $perms &= ~MSZ_FORUM_PERM_SET_WRITE;

$canDeleteAny = perms_check($perms, MSZ_FORUM_PERM_DELETE_ANY_POST);

if($topicInfo->isDeleted() && !$canDeleteAny) {
    echo render_error(404);
    return;
}

if(!perms_check($perms, MSZ_FORUM_PERM_VIEW_FORUM)) {
    echo render_error(403);
    return;
}

$topicPostsTotal = $topicInfo->getActualPostCount(true);
$topicIsFrozen = $topicInfo->isArchived() || $topicInfo->isDeleted();
$canDeleteOwn = !$topicIsFrozen && !$topicInfo->isLocked() && perms_check($perms, MSZ_FORUM_PERM_DELETE_POST);
$canBumpTopic = !$topicIsFrozen && perms_check($perms, MSZ_FORUM_PERM_BUMP_TOPIC);
$canLockTopic = !$topicIsFrozen && perms_check($perms, MSZ_FORUM_PERM_LOCK_TOPIC);
$canNukeOrRestore = $canDeleteAny && $topicInfo->isDeleted();
$canDelete = !$topicInfo->isDeleted() && (
    $canDeleteAny || (
        $topicPostsTotal > 0
        && $topicPostsTotal <= MSZ_FORUM_TOPIC_DELETE_POST_LIMIT
        && $canDeleteOwn
        && $topicInfo->getUserId() === $topicUserId
    )
);

$validModerationModes = [
    'delete', 'restore', 'nuke',
    'bump', 'lock', 'unlock',
];

if(in_array($moderationMode, $validModerationModes, true)) {
    $redirect = !empty($_SERVER['HTTP_REFERER']) && empty($_SERVER['HTTP_X_MISUZU_XHR']) ? $_SERVER['HTTP_REFERER'] : '';
    $isXHR = !$redirect;

    if($isXHR) {
        header('Content-Type: application/json; charset=utf-8');
    } elseif(!is_local_url($redirect)) {
        echo render_info('Possible request forgery detected.', 403);
        return;
    }

    if(!CSRF::validateRequest()) {
        echo render_info_or_json($isXHR, "Couldn't verify this request, please refresh the page and try again.", 403);
        return;
    }

    header(CSRF::header());

    if(!UserSession::hasCurrent()) {
        echo render_info_or_json($isXHR, 'You must be logged in to manage posts.', 401);
        return;
    }

    if($topicUser->isBanned()) {
        echo render_info_or_json($isXHR, 'You have been banned, check your profile for more information.', 403);
        return;
    }
    if($topicUser->isSilenced()) {
        echo render_info_or_json($isXHR, 'You have been silenced, check your profile for more information.', 403);
        return;
    }

    switch($moderationMode) {
        case 'delete':
            $canDeleteCode = forum_topic_can_delete($topicInfo, $topicUserId);
            $canDeleteMsg = '';
            $responseCode = 200;

            switch($canDeleteCode) {
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

            if($canDeleteCode !== MSZ_E_FORUM_TOPIC_DELETE_OK) {
                if($isXHR) {
                    http_response_code($responseCode);
                    echo json_encode([
                        'success' => false,
                        'topic_id' => $topicInfo->getId(),
                        'code' => $canDeleteCode,
                        'message' => $canDeleteMsg,
                    ]);
                    break;
                }

                echo render_info($canDeleteMsg, $responseCode);
                break;
            }

            if(!$isXHR) {
                if(!isset($_GET['confirm'])) {
                    Template::render('forum.confirm', [
                        'title' => 'Confirm topic deletion',
                        'class' => 'far fa-trash-alt',
                        'message' => sprintf('You are about to delete topic #%d. Are you sure about that?', $topicInfo->getId()),
                        'params' => [
                            't' => $topicInfo->getId(),
                            'm' => 'delete',
                        ],
                    ]);
                    break;
                } elseif(!$submissionConfirmed) {
                    url_redirect(
                        'forum-topic',
                        ['topic' => $topicInfo->getId()]
                    );
                    break;
                }
            }

            $deleteTopic = forum_topic_delete($topicInfo->getId());

            if($deleteTopic) {
                AuditLog::create(AuditLog::FORUM_TOPIC_DELETE, [$topicInfo->getId()]);
            }

            if($isXHR) {
                echo json_encode([
                    'success' => $deleteTopic,
                    'topic_id' => $topicInfo->getId(),
                    'message' => $deleteTopic ? 'Topic deleted!' : 'Failed to delete topic.',
                ]);
                break;
            }

            if(!$deleteTopic) {
                echo render_error(500);
                break;
            }

            url_redirect('forum-category', [
                'forum' => $topicInfo->getCategoryId(),
            ]);
            break;

        case 'restore':
            if(!$canNukeOrRestore) {
                echo render_error(403);
                break;
            }

            if(!$isXHR) {
                if(!isset($_GET['confirm'])) {
                    Template::render('forum.confirm', [
                        'title' => 'Confirm topic restore',
                        'class' => 'fas fa-magic',
                        'message' => sprintf('You are about to restore topic #%d. Are you sure about that?', $topicInfo->getId()),
                        'params' => [
                            't' => $topicInfo->getId(),
                            'm' => 'restore',
                        ],
                    ]);
                    break;
                } elseif(!$submissionConfirmed) {
                    url_redirect('forum-topic', [
                        'topic' => $topicInfo->getId(),
                    ]);
                    break;
                }
            }

            $restoreTopic = forum_topic_restore($topicInfo->getId());

            if(!$restoreTopic) {
                echo render_error(500);
                break;
            }

            AuditLog::create(AuditLog::FORUM_TOPIC_RESTORE, [$topicInfo->getId()]);
            http_response_code(204);

            if(!$isXHR) {
                url_redirect('forum-category', [
                    'forum' => $topicInfo->getCategoryId(),
                ]);
            }
            break;

        case 'nuke':
            if(!$canNukeOrRestore) {
                echo render_error(403);
                break;
            }

            if(!$isXHR) {
                if(!isset($_GET['confirm'])) {
                    Template::render('forum.confirm', [
                        'title' => 'Confirm topic nuke',
                        'class' => 'fas fa-radiation',
                        'message' => sprintf('You are about to PERMANENTLY DELETE topic #%d. Are you sure about that?', $topicInfo->getId()),
                        'params' => [
                            't' => $topicInfo->getId(),
                            'm' => 'nuke',
                        ],
                    ]);
                    break;
                } elseif(!$submissionConfirmed) {
                    url_redirect('forum-topic', [
                        'topic' => $topicInfo->getId(),
                    ]);
                    break;
                }
            }

            $nukeTopic = forum_topic_nuke($topicInfo->getId());

            if(!$nukeTopic) {
                echo render_error(500);
                break;
            }

            AuditLog::create(AuditLog::FORUM_TOPIC_NUKE, [$topicInfo->getId()]);
            http_response_code(204);

            if(!$isXHR) {
                url_redirect('forum-category', [
                    'forum' => $topicInfo->getCategoryId(),
                ]);
            }
            break;

        case 'bump':
            if($canBumpTopic) {
                $topicInfo->bumpTopic();
                AuditLog::create(AuditLog::FORUM_TOPIC_BUMP, [$topicInfo->getId()]);
            }

            url_redirect('forum-topic', [
                'topic' => $topicInfo->getId(),
            ]);
            break;

        case 'lock':
            if($canLockTopic && !$topicInfo->isLocked()) {
                $topicInfo->setLocked(true);
                AuditLog::create(AuditLog::FORUM_TOPIC_LOCK, [$topicInfo->getId()]);
            }

            url_redirect('forum-topic', [
                'topic' => $topicInfo->getId(),
            ]);
            break;

        case 'unlock':
            if($canLockTopic && $topicInfo->isLocked()) {
                $topicInfo->setLocked(false);
                AuditLog::create(AuditLog::FORUM_TOPIC_UNLOCK, [$topicInfo->getId()]);
            }

            url_redirect('forum-topic', [
                'topic' => $topicInfo->getId(),
            ]);
            break;
    }
    return;
}

$topicPagination = new Pagination($topicInfo->getActualPostCount($canDeleteAny), \Misuzu\Forum\ForumPost::PER_PAGE, 'page');

if(isset($postInfo['preceeding_post_count'])) {
    $preceedingPosts = $postInfo['preceeding_post_count'];

    if($canDeleteAny) {
        $preceedingPosts += $postInfo['preceeding_post_deleted_count'];
    }

    $topicPagination->setPage(floor($preceedingPosts / $topicPagination->getRange()), true);
}

if(!$topicPagination->hasValidOffset()) {
    echo render_error(404);
    return;
}

$canReply = !$topicInfo->isArchived() && !$topicInfo->isLocked() && !$topicInfo->isDeleted() && perms_check($perms, MSZ_FORUM_PERM_CREATE_POST);

forum_topic_mark_read($topicUserId, $topicInfo->getId(), $topicInfo->getCategoryId());

Template::render('forum.topic', [
    'topic_perms' => $perms,
    'topic_info' => $topicInfo,
    'can_reply' => $canReply,
    'topic_pagination' => $topicPagination,
    'topic_can_delete' => $canDelete,
    'topic_can_view_deleted' => $canDeleteAny,
    'topic_can_nuke_or_restore' => $canNukeOrRestore,
    'topic_can_bump' => $canBumpTopic,
    'topic_can_lock' => $canLockTopic,
]);
