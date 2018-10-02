<?php
use Misuzu\Database;

require_once __DIR__ . '/../misuzu.php';

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

if ($app->getUserId() < 1) {
    echo render_info_or_json($isXHR, 'You must be logged in to manage comments.', 401);
    return;
}

$commentPerms = comments_get_perms($app->getUserId());

switch ($_GET['m'] ?? null) {
    case 'vote':
        $comment = (int)($_GET['c'] ?? 0);

        if ($comment < 1) {
            echo render_info_or_json($isXHR, 'Missing data.', 400);
            break;
        }

        $vote = (int)($_GET['v'] ?? 0);

        if (!array_key_exists($vote, MSZ_COMMENTS_VOTE_TYPES)) {
            echo render_info_or_json($isXHR, 'Invalid vote action.', 400);
            break;
        }

        $vote = MSZ_COMMENTS_VOTE_TYPES[(int)($_GET['v'] ?? 0)];
        $voteResult = comments_vote_add(
            $comment,
            $app->getUserId(),
            $vote
        );

        if (!$isXHR) {
            header('Location: ' . $redirect . '#comment-' . $comment);
            break;
        }

        echo json_encode(comments_votes_get($comment));
        break;

    case 'delete':
        $comment = (int)($_GET['c'] ?? 0);

        if ($comment < 1) {
            echo render_info_or_json($isXHR, 'Missing data.', 400);
            break;
        }

        if (!$commentPerms['can_delete']) {
            echo render_info_or_json($isXHR, "You're not allowed to delete comments.", 403);
            break;
        }

        if (!$commentPerms['can_delete_any']
            && !comments_post_check_ownership($comment, $app->getUserId())) {
            echo render_info_or_json($isXHR, "You're not allowed to delete comments made by others.", 403);
            break;
        }

        if (!comments_post_delete($comment)) {
            echo render_info_or_json($isXHR, 'Failed to delete comment.', 500);
            break;
        }

        if ($redirect) {
            header('Location: ' . $redirect);
            break;
        }

        echo json_encode([
            'comment_id' => (int)$comment,
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

        $categoryId = (int)($_POST['comment']['category'] ?? 0);
        $category = comments_category_info($categoryId);

        if (!$category) {
            echo render_info_or_json($isXHR, 'This comment category doesn\'t exist.', 404);
            break;
        }

        if (!is_null($category['category_locked']) && !$commentPerms['can_lock']) {
            echo render_info_or_json($isXHR, 'This comment category has been locked.', 403);
            break;
        }

        $commentText = $_POST['comment']['text'] ?? '';
        $commentLock = !empty($_POST['comment']['lock']) && $commentPerms['can_lock'];
        $commentPin = !empty($_POST['comment']['pin']) && $commentPerms['can_pin'];
        $commentReply = (int)($_POST['comment']['reply'] ?? 0);

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
            $app->getUserId(),
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
            header('Location: ' . $redirect . '#comment-' . $commentId);
            break;
        }

        echo json_encode(comments_post_get($commentId));
        break;

    default:
        echo render_info_or_json($isXHR, 'Not found.', 404);
}
