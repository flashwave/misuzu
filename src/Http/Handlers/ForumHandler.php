<?php
namespace Misuzu\Http\Handlers;

use Misuzu\CSRF;

final class ForumHandler extends Handler {
    public function markAsReadGET(Response $response, Request $request): void {
        $query = $request->getQueryParams();
        $forumId = isset($query['forum']) && is_string($query['forum']) ? (int)$query['forum'] : null;
        $response->setTemplate('confirm', [
            'title' => 'Mark forum as read',
            'message' => 'Are you sure you want to mark ' . ($forumId === null ? 'the entire' : 'this') . ' forum as read?',
            'return' => url($forumId ? 'forum-category' : 'forum-index', ['forum' => $forumId]),
            'params' => [
                'forum' => $forumId,
            ]
        ]);
    }

    public function markAsReadPOST(Response $response, Request $request) {
        $body = $request->getParsedBody();
        $forumId = isset($body['forum']) && is_string($body['forum']) ? (int)$body['forum'] : null;
        forum_mark_read($forumId, user_session_current('user_id'));

        $response->redirect(
            url($forumId ? 'forum-category' : 'forum-index', ['forum' => $forumId]),
            false,
            $request->hasHeader('X-Misuzu-XHR')
        );
    }
}
