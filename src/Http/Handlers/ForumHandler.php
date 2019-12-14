<?php
namespace Misuzu\Http\Handlers;

final class ForumHandler extends Handler {
    public function markAsRead(Response $response, Request $request) {

        if($request->getMethod() === 'GET') {
            $response->setTemplate('confirm', [
                'message' => 'Are you sure you want to mark the entire forum as read?',
                'return' => url('forum-index'),
            ]);
            return;
        }

        return 'now POSTing';
    }
}
