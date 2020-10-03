<?php
namespace Misuzu\Http\Handlers\Forum;

use HttpResponse;
use HttpRequest;
use Misuzu\Forum\ForumCategory;
use Misuzu\Forum\ForumCategoryNotFoundException;
use Misuzu\Users\User;

class ForumIndexHandler extends ForumHandler {
    public function index(HttpResponse $response): void {
        $response->setTemplate('forum.index', [
            'forum_root' => ForumCategory::root(),
        ]);
    }

    public function markAsRead(HttpResponse $response, HttpRequest $request) {
        try {
            $categoryInfo = ForumCategory::byId(
                (int)($request->getBodyParam('forum', FILTER_SANITIZE_NUMBER_INT) ?? $request->getQueryParam('forum', FILTER_SANITIZE_NUMBER_INT))
            );
        } catch(ForumCategoryNotFoundException $ex) {
            return 404;
        }

        if($request->getMethod() === 'GET') {
            $response->setTemplate('confirm', [
                'title' => 'Mark forum as read',
                'message' => 'Are you sure you want to mark ' . ($categoryInfo->isRoot() ? 'the entire' : 'this') . ' forum as read?',
                'return' => url($categoryInfo->isRoot() ? 'forum-index' : 'forum-category', ['forum' => $categoryInfo->getId()]),
                'params' => [
                    'forum' => $categoryInfo->getId(),
                ]
            ]);
            return;
        }

        $categoryInfo->markAsRead(User::getCurrent());

        $response->redirect(
            url($categoryInfo->isRoot() ? 'forum-index' : 'forum-category', ['forum' => $categoryInfo->getId()]),
            false,
            $request->hasHeader('X-Misuzu-XHR')
        );
    }

    public function legacy(HttpResponse $response, HttpRequest $request): void {
        if($request->getQueryParam('m') === 'mark') {
            $forumId = (int)$request->getQueryParam('f', FILTER_SANITIZE_NUMBER_INT);
            $response->redirect(url($forumId < 1 ? 'forum-mark-global' : 'forum-mark-single', ['forum' => $forumId]));
            return;
        }

        $response->redirect(url('forum-index'));
    }
}
