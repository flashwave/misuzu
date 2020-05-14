<?php
namespace Misuzu\Http\Handlers;

use HttpResponse;
use HttpRequest;
use Misuzu\CSRF;

final class ForumHandler extends Handler {
    public function markAsReadGET(HttpResponse $response, HttpRequest $request): void {
        $forumId = (int)$request->getQueryParam('forum', FILTER_SANITIZE_NUMBER_INT);
        $response->setTemplate('confirm', [
            'title' => 'Mark forum as read',
            'message' => 'Are you sure you want to mark ' . ($forumId === null ? 'the entire' : 'this') . ' forum as read?',
            'return' => url($forumId ? 'forum-category' : 'forum-index', ['forum' => $forumId]),
            'params' => [
                'forum' => $forumId,
            ]
        ]);
    }

    public function markAsReadPOST(HttpResponse $response, HttpRequest $request) {
        $forumId = (int)$request->getBodyParam('forum', FILTER_SANITIZE_NUMBER_INT);
        forum_mark_read($forumId, user_session_current('user_id'));

        $response->redirect(
            url($forumId ? 'forum-category' : 'forum-index', ['forum' => $forumId]),
            false,
            $request->hasHeader('X-Misuzu-XHR')
        );
    }
}
