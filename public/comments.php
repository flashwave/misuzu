<?php
use Misuzu\Database;

require_once __DIR__ . '/../misuzu.php';

// if false, display informational pages instead of outputting json.
$isXHR = !empty($_SERVER['HTTP_MISUZU_XHR_REQUEST']);

if ($isXHR || $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
}

if ($app->getUserId() < 1) {
    echo render_info_or_json($isXHR, 'You must be logged in to manage comments.', 401);
    return;
}

$redirect = !$isXHR && !empty($_SERVER['HTTP_REFERER'])
    && is_local_url($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$commentPerms = comments_get_perms($app->getUserId());
$commentId = (int)($_REQUEST['comment_id'] ?? 0);

if (isset($_POST['vote']) && array_key_exists((int)$_POST['vote'], MSZ_COMMENTS_VOTE_TYPES)) {
    echo comments_vote_add(
        $commentId,
        $app->getUserId(),
        MSZ_COMMENTS_VOTE_TYPES[(int)$_POST['vote']]
    );
    return;
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if ($commentId < 1) {
            echo render_info_or_json(true, 'Missing data.', 400);
            break;
        }

        switch ($_GET['fetch'] ?? '') {
            case 'replies':
                echo json_encode(comments_post_replies($commentId));
                break;

            default:
                echo json_encode(comments_post_get($commentId));
        }
        break;

    case 'POST':
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
        }

        if (!is_null($category['category_locked']) || !$commentPerms['can_lock']) {
            echo render_info_or_json($isXHR, 'This comment category has been locked.', 403);
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

        if (strlen($commentText) > 5000) {
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

    case 'PATCH':
        method_patch:
        if ($commentId < 1) {
            echo render_info_or_json($isXHR, 'Missing data.', 400);
            break;
        }

        if (!$commentPerms['can_edit']) {
            echo render_info_or_json($isXHR, "You're not allowed to edit comments.", 403);
            break;
        }

        if (!$commentPerms['can_edit_any']
            && !comments_post_check_ownership($commentId, $app->getUserId())) {
            echo render_info_or_json($isXHR, "You're not allowed to delete comments made by others.", 403);
            break;
        }

        if ($redirect) {
            header('Location: ' . $redirect . '#comment-' . $commentId);
            break;
        }

        var_dump($_POST);
        break;

    case 'DELETE':
        method_delete:
        if ($commentId < 1) {
            echo render_info_or_json($isXHR, 'Missing data.', 400);
            break;
        }

        if (!$commentPerms['can_delete']) {
            echo render_info_or_json($isXHR, "You're not allowed to delete comments.", 403);
            break;
        }

        if (!$commentPerms['can_delete_any']
            && !comments_post_check_ownership($commentId, $app->getUserId())) {
            echo render_info_or_json($isXHR, "You're not allowed to delete comments made by others.", 403);
            break;
        }

        if (!comments_post_delete($commentId)) {
            echo render_info_or_json($isXHR, 'Failed to delete comment.', 500);
            break;
        }

        if ($redirect) {
            header('Location: ' . $redirect);
            break;
        }

        echo render_info_or_json($isXHR, 'Comment deleted.');
        break;

    default:
        echo render_info_or_json($isXHR, 'Invalid request method.', 405);
}
