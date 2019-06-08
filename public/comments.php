<?php
require_once '../misuzu.php';

// basing whether or not this is an xhr request on whether a referrer header is present
// this page is never directy accessed, under normal circumstances
$redirect = !empty($_SERVER['HTTP_REFERER']) && empty($_SERVER['HTTP_X_MISUZU_XHR']) ? $_SERVER['HTTP_REFERER'] : '';
$isXHR = !$redirect;

if ($isXHR) {
    header('Content-Type: application/json; charset=utf-8');
} elseif (!is_local_url($redirect)) {
    echo render_info('Possible request forgery detected.', 403);
    return;
}

if (!csrf_verify('comments', $_REQUEST['csrf'] ?? '')) {
    echo render_info_or_json($isXHR, "Couldn't verify this request, please refresh the page and try again.", 403);
    return;
}

if (!user_session_active()) {
    echo render_info_or_json($isXHR, 'You must be logged in to manage comments.', 401);
    return;
}

$currentUserId = user_session_current('user_id', 0);

if (user_warning_check_expiration($currentUserId, MSZ_WARN_BAN) > 0) {
    echo render_info_or_json($isXHR, 'You have been banned, check your profile for more information.', 403);
    return;
}
if (user_warning_check_expiration($currentUserId, MSZ_WARN_SILENCE) > 0) {
    echo render_info_or_json($isXHR, 'You have been silenced, check your profile for more information.', 403);
    return;
}

header(csrf_http_header('comments'));
$commentPerms = comments_get_perms($currentUserId);

$commentId = !empty($_GET['c']) && is_string($_GET['c']) ? (int)$_GET['c'] : 0;
$commentMode = !empty($_GET['m']) && is_string($_GET['m']) ? (string)$_GET['m'] : '';
$commentVote = !empty($_GET['v']) && is_string($_GET['v']) ? (int)$_GET['v'] : MSZ_COMMENTS_VOTE_INDIFFERENT;

switch ($commentMode) {
    case 'pin':
    case 'unpin':
        if (!$commentPerms['can_pin']) {
            echo render_info_or_json($isXHR, "You're not allowed to pin comments.", 403);
            break;
        }

        $commentInfo = comments_post_get($commentId, false);

        if (!$commentInfo || $commentInfo['comment_deleted'] !== null) {
            echo render_info_or_json($isXHR, "This comment doesn't exist!", 400);
            break;
        }

        if ($commentInfo['comment_reply_to'] !== null) {
            echo render_info_or_json($isXHR, "You can't pin replies!", 400);
            break;
        }

        $isPinning = $commentMode === 'pin';

        if ($isPinning && !empty($commentInfo['comment_pinned'])) {
            echo render_info_or_json($isXHR, 'This comment is already pinned.', 400);
            break;
        } elseif (!$isPinning && empty($commentInfo['comment_pinned'])) {
            echo render_info_or_json($isXHR, "This comment isn't pinned yet.", 400);
            break;
        }

        $commentPinned = comments_pin_status($commentInfo['comment_id'], $isPinning);

        if (!$isXHR) {
            redirect($redirect . '#comment-' . $commentInfo['comment_id']);
            break;
        }

        echo json_encode([
            'comment_id' => $commentInfo['comment_id'],
            'comment_pinned' => $commentPinned,
        ]);
        break;

    case 'vote':
        if (!$commentPerms['can_vote']) {
            echo render_info_or_json($isXHR, "You're not allowed to vote on comments.", 403);
            break;
        }

        if (!comments_vote_type_valid($commentVote)) {
            echo render_info_or_json($isXHR, 'Invalid vote action.', 400);
            break;
        }

        $commentInfo = comments_post_get($commentId, false);

        if (!$commentInfo || $commentInfo['comment_deleted'] !== null) {
            echo render_info_or_json($isXHR, "This comment doesn't exist!", 400);
            break;
        }

        $voteResult = comments_vote_add(
            $commentInfo['comment_id'],
            user_session_current('user_id', 0),
            $commentVote
        );

        if (!$isXHR) {
            redirect($redirect . '#comment-' . $commentInfo['comment_id']);
            break;
        }

        echo json_encode(comments_votes_get($commentInfo['comment_id']));
        break;

    case 'delete':
        if (!$commentPerms['can_delete']) {
            echo render_info_or_json($isXHR, "You're not allowed to delete comments.", 403);
            break;
        }

        $commentInfo = comments_post_get($commentId, false);

        if (!$commentInfo) {
            echo render_info_or_json($isXHR, "This comment doesn't exist.", 400);
            break;
        }

        $isOwnComment = (int)$commentInfo['user_id'] === $currentUserId;
        $isModAction = $commentPerms['can_delete_any'] && !$isOwnComment;

        if ($commentInfo['comment_deleted'] !== null) {
            echo render_info_or_json(
                $isXHR,
                $commentPerms['can_delete_any'] ? 'This comment is already marked for deletion.' : "This comment doesn't exist.",
                400
            );
            break;
        }

        if (!$isModAction && !$isOwnComment) {
            echo render_info_or_json($isXHR, "You're not allowed to delete comments made by others.", 403);
            break;
        }

        if (!comments_post_delete($commentInfo['comment_id'])) {
            echo render_info_or_json($isXHR, 'Failed to delete comment.', 500);
            break;
        }

        if ($isModAction) {
            audit_log(MSZ_AUDIT_COMMENT_ENTRY_DELETE_MOD, $currentUserId, [
                $commentInfo['comment_id'],
                (int)($commentInfo['user_id'] ?? 0),
                $commentInfo['username'] ?? '(Deleted User)',
            ]);
        } else {
            audit_log(MSZ_AUDIT_COMMENT_ENTRY_DELETE, $currentUserId, [$commentInfo['comment_id']]);
        }

        if ($redirect) {
            redirect($redirect);
            break;
        }

        echo json_encode([
            'id' => $commentInfo['comment_id'],
        ]);
        break;

    case 'restore':
        if (!$commentPerms['can_delete_any']) {
            echo render_info_or_json($isXHR, "You're not allowed to restore deleted comments.", 403);
            break;
        }

        $commentInfo = comments_post_get($commentId, false);

        if (!$commentInfo) {
            echo render_info_or_json($isXHR, "This comment doesn't exist.", 400);
            break;
        }

        if ($commentInfo['comment_deleted'] === null) {
            echo render_info_or_json($isXHR, "This comment isn't in a deleted state.", 400);
            break;
        }

        if (!comments_post_delete($commentInfo['comment_id'], false)) {
            echo render_info_or_json($isXHR, 'Failed to restore comment.', 500);
            break;
        }

        audit_log(MSZ_AUDIT_COMMENT_ENTRY_RESTORE, $currentUserId, [
            $commentInfo['comment_id'],
            (int)($commentInfo['user_id'] ?? 0),
            $commentInfo['username'] ?? '(Deleted User)',
        ]);

        if ($redirect) {
            redirect($redirect . '#comment-' . $commentInfo['comment_id']);
            break;
        }

        echo json_encode([
            'id' => $commentInfo['comment_id'],
        ]);
        break;

    case 'create':
        if (!$commentPerms['can_comment']) {
            echo render_info_or_json($isXHR, "You're not allowed to post comments.", 403);
            break;
        }

        if (empty($_POST['comment']) || !is_array($_POST['comment'])) {
            echo render_info_or_json($isXHR, 'Missing data.', 400);
            break;
        }

        $categoryId = !empty($_POST['comment']['category']) && is_string($_POST['comment']['category']) ? (int)$_POST['comment']['category'] : 0;
        $category = comments_category_info($categoryId);

        if (!$category) {
            echo render_info_or_json($isXHR, 'This comment category doesn\'t exist.', 404);
            break;
        }

        if (!is_null($category['category_locked']) && !$commentPerms['can_lock']) {
            echo render_info_or_json($isXHR, 'This comment category has been locked.', 403);
            break;
        }

        $commentText = !empty($_POST['comment']['text']) && is_string($_POST['comment']['text']) ? $_POST['comment']['text'] : '';
        $commentLock = !empty($_POST['comment']['lock']) && $commentPerms['can_lock'];
        $commentPin = !empty($_POST['comment']['pin']) && $commentPerms['can_pin'];
        $commentReply = !empty($_POST['comment']['reply']) && is_string($_POST['comment']['reply']) ? (int)$_POST['comment']['reply'] : 0;

        if ($commentLock) {
            comments_category_lock($categoryId, is_null($category['category_locked']));
        }

        if (strlen($commentText) > 0) {
            $commentText = preg_replace("/[\r\n]{2,}/", "\n", $commentText);
        } else {
            if ($commentPerms['can_lock']) {
                echo render_info_or_json($isXHR, 'The action has been processed.');
            } else {
                echo render_info_or_json($isXHR, 'Your comment is too short.', 400);
            }
            break;
        }

        if (mb_strlen($commentText) > 5000) {
            echo render_info_or_json($isXHR, 'Your comment is too long.', 400);
            break;
        }

        if ($commentReply > 0 && !comments_post_exists($commentReply)) {
            echo render_info_or_json($isXHR, 'The comment you tried to reply to does not exist.', 404);
            break;
        }

        $commentId = comments_post_create(
            user_session_current('user_id', 0),
            $categoryId,
            $commentText,
            $commentPin,
            $commentReply
        );

        if ($commentId < 1) {
            echo render_info_or_json($isXHR, 'Something went horribly wrong.', 500);
            break;
        }

        if ($redirect) {
            redirect($redirect . '#comment-' . $commentId);
            break;
        }

        echo json_encode(comments_post_get($commentId));
        break;

    default:
        echo render_info_or_json($isXHR, 'Not found.', 404);
}
