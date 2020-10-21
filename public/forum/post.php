<?php
namespace Misuzu;

use Misuzu\AuditLog;
use Misuzu\Forum\ForumPost;
use Misuzu\Forum\ForumPostNotFoundException;
use Misuzu\Users\User;
use Misuzu\Users\UserSession;

require_once '../../misuzu.php';

$postId = !empty($_GET['p']) && is_string($_GET['p']) ? (int)$_GET['p'] : 0;
$postMode = !empty($_GET['m']) && is_string($_GET['m']) ? (string)$_GET['m'] : '';
$submissionConfirmed = !empty($_GET['confirm']) && is_string($_GET['confirm']) && $_GET['confirm'] === '1';

// basing whether or not this is an xhr request on whether a referrer header is present
// this page is never directy accessed, under normal circumstances
$redirect = !empty($_SERVER['HTTP_REFERER']) && empty($_SERVER['HTTP_X_MISUZU_XHR']) ? $_SERVER['HTTP_REFERER'] : '';
$isXHR = !$redirect;

if($isXHR) {
    header('Content-Type: application/json; charset=utf-8');
} elseif(!is_local_url($redirect)) {
    echo render_info('Possible request forgery detected.', 403);
    return;
}

$postRequestVerified = CSRF::validateRequest();

if(!empty($postMode) && !UserSession::hasCurrent()) {
    echo render_info_or_json($isXHR, 'You must be logged in to manage posts.', 401);
    return;
}

$currentUser = User::getCurrent();
$currentUserId = $currentUser === null ? 0 : $currentUser->getId();

if(isset($currentUser) && $currentUser->isBanned()) {
    echo render_info_or_json($isXHR, 'You have been banned, check your profile for more information.', 403);
    return;
}
if(isset($currentUser) && $currentUser->isSilenced()) {
    echo render_info_or_json($isXHR, 'You have been silenced, check your profile for more information.', 403);
    return;
}

if($isXHR) {
    if(!$postRequestVerified) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Possible request forgery detected.',
        ]);
        return;
    }

    header(CSRF::header());
}

try {
    $postInfo = ForumPost::byId($postId);
    $perms = forum_perms_get_user($postInfo->getCategoryId(), $currentUserId)[MSZ_FORUM_PERMS_GENERAL];
} catch(ForumPostNotFoundException $ex) {
    $postInfo = null;
    $perms = 0;
}

switch($postMode) {
    case 'delete':
        $canDeleteCodes = [
            'view' => 404,
            'deleted' => 404,
            'owner' => 403,
            'age' => 403,
            'permission' => 403,
            '' => 200,
        ];
        $canDelete = $postInfo->canBeDeleted($currentUser);
        $canDeleteMsg = ForumPost::canBeDeletedErrorString($canDelete);
        $responseCode = $canDeleteCodes[$canDelete] ?? 500;

        if($canDelete !== '') {
            if($isXHR) {
                http_response_code($responseCode);
                echo json_encode([
                    'success' => false,
                    'post_id' => $postInfo->getId(),
                    'code' => $canDelete,
                    'message' => $canDeleteMsg,
                ]);
                break;
            }

            echo render_info($canDeleteMsg, $responseCode);
            break;
        }

        if(!$isXHR) {
            if($postRequestVerified && !$submissionConfirmed) {
                url_redirect('forum-post', [
                    'post' => $postInfo->getId(),
                    'post_fragment' => 'p' . $postInfo->getId(),
                ]);
                break;
            } elseif(!$postRequestVerified) {
                Template::render('forum.confirm', [
                    'title' => 'Confirm post deletion',
                    'class' => 'far fa-trash-alt',
                    'message' => sprintf('You are about to delete post #%d. Are you sure about that?', $postInfo->getId()),
                    'params' => [
                        'p' => $postInfo->getId(),
                        'm' => 'delete',
                    ],
                ]);
                break;
            }
        }

        $postInfo->delete();
        AuditLog::create(AuditLog::FORUM_POST_DELETE, [$postInfo->getId()]);

        if($isXHR) {
            echo json_encode([
                'success' => $deletePost,
                'post_id' => $postInfo->getId(),
                'message' => $deletePost ? 'Post deleted!' : 'Failed to delete post.',
            ]);
            break;
        }

        if(!$deletePost) {
            echo render_error(500);
            break;
        }

        url_redirect('forum-topic', ['topic' => $postInfo->getTopicId()]);
        break;

    case 'nuke':
        if(!perms_check($perms, MSZ_FORUM_PERM_DELETE_ANY_POST)) {
            echo render_error(403);
            break;
        }

        if(!$isXHR) {
            if($postRequestVerified && !$submissionConfirmed) {
                url_redirect('forum-post', [
                    'post' => $postInfo->getId(),
                    'post_fragment' => 'p' . $postInfo->getId(),
                ]);
                break;
            } elseif(!$postRequestVerified) {
                Template::render('forum.confirm', [
                    'title' => 'Confirm post nuke',
                    'class' => 'fas fa-radiation',
                    'message' => sprintf('You are about to PERMANENTLY DELETE post #%d. Are you sure about that?', $postInfo->getId()),
                    'params' => [
                        'p' => $postInfo->getId(),
                        'm' => 'nuke',
                    ],
                ]);
                break;
            }
        }

        $postInfo->nuke();
        AuditLog::create(AuditLog::FORUM_POST_NUKE, [$postInfo->getId()]);
        http_response_code(204);

        if(!$isXHR) {
            url_redirect('forum-topic', ['topic' => $postInfo->getTopicId()]);
        }
        break;

    case 'restore':
        if(!perms_check($perms, MSZ_FORUM_PERM_DELETE_ANY_POST)) {
            echo render_error(403);
            break;
        }

        if(!$isXHR) {
            if($postRequestVerified && !$submissionConfirmed) {
                url_redirect('forum-post', [
                    'post' => $postInfo->getId(),
                    'post_fragment' => 'p' . $postInfo->getId(),
                ]);
                break;
            } elseif(!$postRequestVerified) {
                Template::render('forum.confirm', [
                    'title' => 'Confirm post restore',
                    'class' => 'fas fa-magic',
                    'message' => sprintf('You are about to restore post #%d. Are you sure about that?', $postInfo->getId()),
                    'params' => [
                        'p' => $postInfo->getId(),
                        'm' => 'restore',
                    ],
                ]);
                break;
            }
        }

        $postInfo->restore();
        AuditLog::create(AuditLog::FORUM_POST_RESTORE, [$postInfo->getId()]);
        http_response_code(204);

        if(!$isXHR) {
            url_redirect('forum-topic', ['topic' => $postInfo->getTopicId()]);
        }
        break;
}
