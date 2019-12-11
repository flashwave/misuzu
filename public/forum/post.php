<?php
namespace Misuzu;

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

if(!empty($postMode) && !user_session_active()) {
    echo render_info_or_json($isXHR, 'You must be logged in to manage posts.', 401);
    return;
}

$currentUserId = (int)user_session_current('user_id', 0);

if(user_warning_check_expiration($currentUserId, MSZ_WARN_BAN) > 0) {
    echo render_info_or_json($isXHR, 'You have been banned, check your profile for more information.', 403);
    return;
}
if(user_warning_check_expiration($currentUserId, MSZ_WARN_SILENCE) > 0) {
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

$postInfo = forum_post_get($postId, true);
$perms = empty($postInfo)
    ? 0
    : forum_perms_get_user($postInfo['forum_id'], $currentUserId)[MSZ_FORUM_PERMS_GENERAL];

switch($postMode) {
    case 'delete':
        $canDelete = forum_post_can_delete($postInfo, $currentUserId);
        $canDeleteMsg = '';
        $responseCode = 200;

        switch($canDelete) {
            case MSZ_E_FORUM_POST_DELETE_USER: // i don't think this is ever reached but we may as well have it
                $responseCode = 401;
                $canDeleteMsg = 'You must be logged in to delete posts.';
                break;
            case MSZ_E_FORUM_POST_DELETE_POST:
                $responseCode = 404;
                $canDeleteMsg = "This post doesn't exist.";
                break;
            case MSZ_E_FORUM_POST_DELETE_DELETED:
                $responseCode = 404;
                $canDeleteMsg = 'This post has already been marked as deleted.';
                break;
            case MSZ_E_FORUM_POST_DELETE_OWNER:
                $responseCode = 403;
                $canDeleteMsg = 'You can only delete your own posts.';
                break;
            case MSZ_E_FORUM_POST_DELETE_OLD:
                $responseCode = 401;
                $canDeleteMsg = 'This post has existed for too long. Ask a moderator to remove if it absolutely necessary.';
                break;
            case MSZ_E_FORUM_POST_DELETE_PERM:
                $responseCode = 401;
                $canDeleteMsg = 'You are not allowed to delete posts.';
                break;
            case MSZ_E_FORUM_POST_DELETE_OP:
                $responseCode = 403;
                $canDeleteMsg = 'This is the opening post of a topic, it may not be deleted without deleting the entire topic as well.';
                break;
            case MSZ_E_FORUM_POST_DELETE_OK:
                break;
            default:
                $responseCode = 500;
                $canDeleteMsg = sprintf('Unknown error \'%d\'', $canDelete);
        }

        if($canDelete !== MSZ_E_FORUM_POST_DELETE_OK) {
            if($isXHR) {
                http_response_code($responseCode);
                echo json_encode([
                    'success' => false,
                    'post_id' => $postInfo['post_id'],
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
                    'post' => $postInfo['post_id'],
                    'post_fragment' => 'p' . $postInfo['post_id'],
                ]);
                break;
            } elseif(!$postRequestVerified) {
                Template::render('forum.confirm', [
                    'title' => 'Confirm post deletion',
                    'class' => 'far fa-trash-alt',
                    'message' => sprintf('You are about to delete post #%d. Are you sure about that?', $postInfo['post_id']),
                    'params' => [
                        'p' => $postInfo['post_id'],
                        'm' => 'delete',
                    ],
                ]);
                break;
            }
        }

        $deletePost = forum_post_delete($postInfo['post_id']);

        if($deletePost) {
            audit_log(MSZ_AUDIT_FORUM_POST_DELETE, $currentUserId, [$postInfo['post_id']]);
        }

        if($isXHR) {
            echo json_encode([
                'success' => $deletePost,
                'post_id' => $postInfo['post_id'],
                'message' => $deletePost ? 'Post deleted!' : 'Failed to delete post.',
            ]);
            break;
        }

        if(!$deletePost) {
            echo render_error(500);
            break;
        }

        url_redirect('forum-topic', ['topic' => $postInfo['topic_id']]);
        break;

    case 'nuke':
        if(!perms_check($perms, MSZ_FORUM_PERM_DELETE_ANY_POST)) {
            echo render_error(403);
            break;
        }

        if(!$isXHR) {
            if($postRequestVerified && !$submissionConfirmed) {
                url_redirect('forum-post', [
                    'post' => $postInfo['post_id'],
                    'post_fragment' => 'p' . $postInfo['post_id'],
                ]);
                break;
            } elseif(!$postRequestVerified) {
                Template::render('forum.confirm', [
                    'title' => 'Confirm post nuke',
                    'class' => 'fas fa-radiation',
                    'message' => sprintf('You are about to PERMANENTLY DELETE post #%d. Are you sure about that?', $postInfo['post_id']),
                    'params' => [
                        'p' => $postInfo['post_id'],
                        'm' => 'nuke',
                    ],
                ]);
                break;
            }
        }

        $nukePost = forum_post_nuke($postInfo['post_id']);

        if(!$nukePost) {
            echo render_error(500);
            break;
        }

        audit_log(MSZ_AUDIT_FORUM_POST_NUKE, $currentUserId, [$postInfo['post_id']]);
        http_response_code(204);

        if(!$isXHR) {
            url_redirect('forum-topic', ['topic' => $postInfo['topic_id']]);
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
                    'post' => $postInfo['post_id'],
                    'post_fragment' => 'p' . $postInfo['post_id'],
                ]);
                break;
            } elseif(!$postRequestVerified) {
                Template::render('forum.confirm', [
                    'title' => 'Confirm post restore',
                    'class' => 'fas fa-magic',
                    'message' => sprintf('You are about to restore post #%d. Are you sure about that?', $postInfo['post_id']),
                    'params' => [
                        'p' => $postInfo['post_id'],
                        'm' => 'restore',
                    ],
                ]);
                break;
            }
        }

        $restorePost = forum_post_restore($postInfo['post_id']);

        if(!$restorePost) {
            echo render_error(500);
            break;
        }

        audit_log(MSZ_AUDIT_FORUM_POST_RESTORE, $currentUserId, [$postInfo['post_id']]);
        http_response_code(204);

        if(!$isXHR) {
            url_redirect('forum-topic', ['topic' => $postInfo['topic_id']]);
        }
        break;

    default: // function as an alt for topic.php?p= by default
        $canDeleteAny = perms_check($perms, MSZ_FORUM_PERM_DELETE_ANY_POST);

        if(!empty($postInfo['post_deleted']) && !$canDeleteAny) {
            echo render_error(404);
            break;
        }

        $postFind = forum_post_find($postInfo['post_id'], user_session_current('user_id', 0));

        if(empty($postFind)) {
            echo render_error(404);
            break;
        }

        if($canDeleteAny) {
            $postInfo['preceeding_post_count'] += $postInfo['preceeding_post_deleted_count'];
        }

        unset($postInfo['preceeding_post_deleted_count']);

        if($isXHR) {
            echo json_encode($postFind);
            break;
        }

        url_redirect('forum-topic', [
            'topic' => $postFind['topic_id'],
            'page' => floor($postFind['preceeding_post_count'] / MSZ_FORUM_POSTS_PER_PAGE) + 1,
        ]);
}
