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

if($topicId < 1 && $postId > 0)
    try {
        $postInfo = ForumPost::byId($postId);
        $topicId = $postInfo->getTopicId();
    } catch(ForumPostNotFoundException $ex) {
        echo render_error(404);
        return;
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
        && $topicPostsTotal <= ForumTopic::DELETE_POST_LIMIT
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
            $canDeleteCodes = [
                'view' => 404,
                'deleted' => 404,
                'owner' => 403,
                'age' => 403,
                'permission' => 403,
                'posts' => 403,
                '' => 200,
            ];
            $canDelete = $topicInfo->canBeDeleted($topicUser);
            $canDeleteMsg = ForumTopic::canBeDeletedErrorString($canDelete);
            $responseCode = $canDeleteCodes[$canDelete] ?? 500;

            if($canDelete !== '') {
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

            $topicInfo->delete();
            AuditLog::create(AuditLog::FORUM_TOPIC_DELETE, [$topicInfo->getId()]);

            if($isXHR) {
                echo json_encode([
                    'success' => true,
                    'topic_id' => $topicInfo->getId(),
                    'message' => 'Topic deleted!',
                ]);
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

            $topicInfo->restore();
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

            $topicInfo->nuke();
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

if(isset($postInfo))
    $topicPagination->setPage($postInfo->getTopicPage($canDeleteAny, $topicPagination->getRange()));

if(!$topicPagination->hasValidOffset()) {
    echo render_error(404);
    return;
}

$canReply = !$topicInfo->isArchived() && !$topicInfo->isLocked() && !$topicInfo->isDeleted() && perms_check($perms, MSZ_FORUM_PERM_CREATE_POST);

$topicInfo->markRead($topicUser);

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
