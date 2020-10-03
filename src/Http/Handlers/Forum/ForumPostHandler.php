<?php
namespace Misuzu\Http\Handlers\Forum;

use HttpResponse;
use HttpRequest;
use Misuzu\Pagination;
use Misuzu\Forum\ForumPost;
use Misuzu\Forum\ForumPostNotFoundException;
use Misuzu\Users\User;

class ForumPostHandler extends ForumHandler {
    public function post(HttpResponse $response, HttpRequest $request, int $postId) {
        try {
            $postInfo = ForumPost::byId($postId);
        } catch(ForumPostNotFoundException $ex) {
            return 404;
        }

        var_dump($postInfo->getId());
    }

    public function edit(HttpResponse $response, HttpRequest $request, int $postId) {
    }

    public function delete(HttpResponse $response, HttpRequest $request, int $postId) {
    }

    public function restore(HttpResponse $response, HttpRequest $request, int $postId) {
    }

    public function nuke(HttpResponse $response, HttpRequest $request, int $postId) {
    }

    public function legacy(HttpResponse $response, HttpRequest $request) {
        $postId  = (int)$request->getQueryParam('p', FILTER_SANITIZE_NUMBER_INT);
        if($postId > 0) {
            $response->redirect(url('forum-post', ['post' => $postId]));
            return;
        }

        return 404;
    }
}
