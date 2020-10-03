<?php
namespace Misuzu\Http\Handlers\Forum;

use HttpResponse;
use HttpRequest;
use Misuzu\Pagination;
use Misuzu\Forum\ForumCategory;
use Misuzu\Forum\ForumCategoryNotFoundException;
use Misuzu\Users\User;

class ForumCategoryHandler extends ForumHandler {
    public function category(HttpResponse $response, HttpRequest $request, int $categoryId) {
        if($categoryId === 0) {
            $response->redirect(url('forum-index'));
            return;
        }

        try {
            $categoryInfo = ForumCategory::byId($categoryId);
        } catch(ForumCategoryNotFoundException $ex) {}

        if(empty($categoryInfo) || ($categoryInfo->isLink() && !$categoryInfo->hasLink()))
            return 404;

        $currentUser = User::getCurrent();

        if(!$categoryInfo->canView($currentUser))
            return 403;

        $perms = forum_perms_get_user($categoryInfo->getId(), $currentUser === null ? 0 : $currentUser->getId())[MSZ_FORUM_PERMS_GENERAL];

        if(isset($currentUser) && $currentUser->hasActiveWarning())
            $perms &= ~MSZ_FORUM_PERM_SET_WRITE;

        if($categoryInfo->isLink()) {
            $categoryInfo->increaseLinkClicks();
            $response->redirect($categoryInfo->getLink());
            return;
        }

        $canViewDeleted = perms_check($perms, MSZ_FORUM_PERM_DELETE_ANY_POST);
        $pagination = new Pagination($categoryInfo->getActualTopicCount($canViewDeleted), 20);

        if(!$pagination->hasValidOffset() && $pagination->getCount() > 0)
            return 404;

        $response->setTemplate('forum.forum', [
            'forum_perms' => $perms,
            'forum_breadcrumbs' => forum_get_breadcrumbs($categoryInfo->getId()),
            'forum_info' => $categoryInfo,
            'forum_pagination' => $pagination,
            'can_view_deleted' => $canViewDeleted,
        ]);
    }

    public function createView(HttpResponse $response, HttpRequest $request, int $categoryId) {
        try {
            $categoryInfo = ForumCategory::byId($categoryId);
        } catch(ForumCategoryNotFoundException $ex) {
            return 404;
        }

        var_dump($categoryInfo->getId());
    }

    public function createAction(HttpResponse $response, HttpRequest $request, int $categoryId) {
        try {
            $categoryInfo = ForumCategory::byId($categoryId);
        } catch(ForumCategoryNotFoundException $ex) {
            return 404;
        }

        var_dump($categoryInfo->getId());
    }

    public function legacy(HttpResponse $response, HttpRequest $request) {
        $categoryId = (int)$request->getQueryParam('f', FILTER_SANITIZE_NUMBER_INT);

        if($categoryId < 0)
            return 404;

        if($categoryId === 0) {
            $response->redirect(url('forum-index'));
            return;
        }

        $response->redirect(url('forum-category', [
            'forum' => $categoryId,
            'page'  => (int)$request->getQueryParam('p', FILTER_SANITIZE_NUMBER_INT),
        ]));
    }
}
