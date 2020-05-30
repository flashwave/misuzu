<?php
namespace Misuzu;

use Misuzu\AuditLog;
use Misuzu\Comments\CommentsCategory;
use Misuzu\Comments\CommentsCategoryNotFoundException;
use Misuzu\Comments\CommentsPost;
use Misuzu\Comments\CommentsPostNotFoundException;
use Misuzu\Comments\CommentsPostSaveFailedException;
use Misuzu\Comments\CommentsVote;
use Misuzu\Users\User;
use Misuzu\Users\UserNotFoundException;

require_once '../misuzu.php';

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

if(!CSRF::validateRequest()) {
    echo render_info_or_json($isXHR, "Couldn't verify this request, please refresh the page and try again.", 403);
    return;
}

$currentUserInfo = User::getCurrent();
if($currentUserInfo === null) {
    echo render_info_or_json($isXHR, 'You must be logged in to manage comments.', 401);
    return;
}

if(user_warning_check_expiration($currentUserInfo->getId(), MSZ_WARN_BAN) > 0) {
    echo render_info_or_json($isXHR, 'You have been banned, check your profile for more information.', 403);
    return;
}
if(user_warning_check_expiration($currentUserInfo->getId(), MSZ_WARN_SILENCE) > 0) {
    echo render_info_or_json($isXHR, 'You have been silenced, check your profile for more information.', 403);
    return;
}

header(CSRF::header());
$commentPerms = $currentUserInfo->commentPerms();

$commentId   = (int)filter_input(INPUT_GET, 'c', FILTER_SANITIZE_NUMBER_INT);
$commentMode =      filter_input(INPUT_GET, 'm');
$commentVote = (int)filter_input(INPUT_GET, 'v', FILTER_SANITIZE_NUMBER_INT);

if($commentId > 0)
    try {
        $commentInfo2 = CommentsPost::byId($commentId);
    } catch(CommentsPostNotFoundException $ex) {
        echo render_info_or_json($isXHR, 'Post not found.', 404);
        return;
    }

switch($commentMode) {
    case 'pin':
    case 'unpin':
        if(!$commentPerms['can_pin'] && !$commentInfo2->isOwner($currentUserInfo)) {
            echo render_info_or_json($isXHR, "You're not allowed to pin comments.", 403);
            break;
        }

        if($commentInfo2->isDeleted()) {
            echo render_info_or_json($isXHR, "This comment doesn't exist!", 400);
            break;
        }

        if($commentInfo2->hasParent()) {
            echo render_info_or_json($isXHR, "You can't pin replies!", 400);
            break;
        }

        $isPinning = $commentMode === 'pin';

        if($isPinning && $commentInfo2->isPinned()) {
            echo render_info_or_json($isXHR, 'This comment is already pinned.', 400);
            break;
        } elseif(!$isPinning && !$commentInfo2->isPinned()) {
            echo render_info_or_json($isXHR, "This comment isn't pinned yet.", 400);
            break;
        }

        $commentInfo2->setPinned($isPinning);
        $commentInfo2->save();

        if(!$isXHR) {
            redirect($redirect . '#comment-' . $commentInfo2->getId());
            break;
        }

        echo json_encode([
            'comment_id'     => $commentInfo2->getId(),
            'comment_pinned' => ($time = $commentInfo2->getPinnedTime()) < 0 ? null : date('Y-m-d H:i:s', $time),
        ]);
        break;

    case 'vote':
        if(!$commentPerms['can_vote'] && !$commentInfo2->isOwner($currentUserInfo)) {
            echo render_info_or_json($isXHR, "You're not allowed to vote on comments.", 403);
            break;
        }

        if($commentInfo2->isDeleted()) {
            echo render_info_or_json($isXHR, "This comment doesn't exist!", 400);
            break;
        }

        if($commentVote > 0)
            $commentInfo2->addPositiveVote($currentUserInfo);
        elseif($commentVote < 0)
            $commentInfo2->addNegativeVote($currentUserInfo);
        else
            $commentInfo2->removeVote($currentUserInfo);

        if(!$isXHR) {
            redirect($redirect . '#comment-' . $commentInfo2->getId());
            break;
        }

        echo json_encode($commentInfo2->votes());
        break;

    case 'delete':
        if(!$commentPerms['can_delete'] && !$commentInfo2->isOwner($currentUserInfo)) {
            echo render_info_or_json($isXHR, "You're not allowed to delete comments.", 403);
            break;
        }

        if($commentInfo2->isDeleted()) {
            echo render_info_or_json(
                $isXHR,
                $commentPerms['can_delete_any'] ? 'This comment is already marked for deletion.' : "This comment doesn't exist.",
                400
            );
            break;
        }

        $isOwnComment = $commentInfo2->getUserId() === $currentUserInfo->getId();
        $isModAction  = $commentPerms['can_delete_any'] && !$isOwnComment;

        if(!$isModAction && !$isOwnComment) {
            echo render_info_or_json($isXHR, "You're not allowed to delete comments made by others.", 403);
            break;
        }

        $commentInfo2->setDeleted(true);
        $commentInfo2->save();

        if($isModAction) {
            AuditLog::create(AuditLog::COMMENT_ENTRY_DELETE_MOD, [
                $commentInfo2->getId(),
                $commentUserId = $commentInfo2->getUserId(),
                ($commentUserId < 1 ? '(Deleted User)' : $commentInfo2->getUser()->getUsername()),
            ]);
        } else {
            AuditLog::create(AuditLog::COMMENT_ENTRY_DELETE, [$commentInfo2->getId()]);
        }

        if($redirect) {
            redirect($redirect);
            break;
        }

        echo json_encode([
            'id' => $commentInfo2->getId(),
        ]);
        break;

    case 'restore':
        if(!$commentPerms['can_delete_any']) {
            echo render_info_or_json($isXHR, "You're not allowed to restore deleted comments.", 403);
            break;
        }

        if(!$commentInfo2->isDeleted()) {
            echo render_info_or_json($isXHR, "This comment isn't in a deleted state.", 400);
            break;
        }

        $commentInfo2->setDeleted(false);
        $commentInfo2->save();

        AuditLog::create(AuditLog::COMMENT_ENTRY_RESTORE, [
            $commentInfo2->getId(),
            $commentUserId = $commentInfo2->getUserId(),
            ($commentUserId < 1 ? '(Deleted User)' : $commentInfo2->getUser()->getUsername()),
        ]);

        if($redirect) {
            redirect($redirect . '#comment-' . $commentInfo2->getId());
            break;
        }

        echo json_encode([
            'id' => $commentInfo2->getId(),
        ]);
        break;

    case 'create':
        if(!$commentPerms['can_comment'] && !$commentInfo2->isOwner($currentUserInfo)) {
            echo render_info_or_json($isXHR, "You're not allowed to post comments.", 403);
            break;
        }

        if(empty($_POST['comment']) || !is_array($_POST['comment'])) {
            echo render_info_or_json($isXHR, 'Missing data.', 400);
            break;
        }

        try {
            $categoryInfo = CommentsCategory::byId(
                isset($_POST['comment']['category']) && is_string($_POST['comment']['category'])
                    ? (int)$_POST['comment']['category']
                    : 0
            );
        } catch(CommentsCategoryNotFoundException $ex) {
            echo render_info_or_json($isXHR, 'This comment category doesn\'t exist.', 404);
            break;
        }

        if($categoryInfo->isLocked() && !$commentPerms['can_lock']) {
            echo render_info_or_json($isXHR, 'This comment category has been locked.', 403);
            break;
        }

        $commentText  = !empty($_POST['comment']['text'])  && is_string($_POST['comment']['text'])  ?      $_POST['comment']['text']  : '';
        $commentReply = !empty($_POST['comment']['reply']) && is_string($_POST['comment']['reply']) ? (int)$_POST['comment']['reply'] : 0;
        $commentLock  = !empty($_POST['comment']['lock'])  && $commentPerms['can_lock'];
        $commentPin   = !empty($_POST['comment']['pin'])   && $commentPerms['can_pin'];

        if($commentLock) {
            $categoryInfo->setLocked(!$categoryInfo->isLocked());
            $categoryInfo->save();
        }

        if(strlen($commentText) > 0) {
            $commentText = preg_replace("/[\r\n]{2,}/", "\n", $commentText);
        } else {
            if($commentPerms['can_lock']) {
                echo render_info_or_json($isXHR, 'The action has been processed.');
            } else {
                echo render_info_or_json($isXHR, 'Your comment is too short.', 400);
            }
            break;
        }

        if(mb_strlen($commentText) > 5000) {
            echo render_info_or_json($isXHR, 'Your comment is too long.', 400);
            break;
        }

        if($commentReply > 0) {
            try {
                $parentCommentInfo = CommentsPost::byId($commentReply);
            } catch(CommentsPostNotFoundException $ex) {
                unset($parentCommentInfo);
            }

            if(!isset($parentCommentInfo) || $parentCommentInfo->isDeleted()) {
                echo render_info_or_json($isXHR, 'The comment you tried to reply to does not exist.', 404);
                break;
            }
        }

        $commentInfo2 = (new CommentsPost)
            ->setUser($currentUserInfo)
            ->setCategory($categoryInfo)
            ->setParsedText($commentText)
            ->setPinned($commentPin);

        if(isset($parentCommentInfo))
            $commentInfo2->setParent($parentCommentInfo);

        try {
            $commentInfo2->save();
        } catch(CommentsPostSaveFailedException $ex) {
            echo render_info_or_json($isXHR, 'Something went horribly wrong.', 500);
            break;
        }

        if($redirect) {
            redirect($redirect . '#comment-' . $commentInfo2->getId());
            break;
        }

        echo json_encode([
            'comment_id'       => $commentInfo2->getId(),
            'category_id'      => $commentInfo2->getCategoryId(),
            'comment_text'     => $commentInfo2->getText(),
            'comment_created'  => ($time = $commentInfo2->getCreatedTime()) < 0 ? null : date('Y-m-d H:i:s', $time),
            'comment_edited'   => ($time = $commentInfo2->getEditedTime())  < 0 ? null : date('Y-m-d H:i:s', $time),
            'comment_deleted'  => ($time = $commentInfo2->getDeletedTime()) < 0 ? null : date('Y-m-d H:i:s', $time),
            'comment_pinned'   => ($time = $commentInfo2->getPinnedTime())  < 0 ? null : date('Y-m-d H:i:s', $time),
            'comment_reply_to' => ($parent = $commentInfo2->getParentId())  < 1 ? null : $parent,
            'user_id'          => ($commentInfo2->getUserId() < 1 ? null       : $commentInfo2->getUser()->getId()),
            'username'         => ($commentInfo2->getUserId() < 1 ? null       : $commentInfo2->getUser()->getUsername()),
            'user_colour'      => ($commentInfo2->getUserId() < 1 ? 0x40000000 : $commentInfo2->getUser()->getColour()->getRaw()),
        ]);
        break;

    default:
        echo render_info_or_json($isXHR, 'Not found.', 404);
}
