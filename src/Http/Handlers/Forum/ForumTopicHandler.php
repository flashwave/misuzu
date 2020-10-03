<?php
namespace Misuzu\Http\Handlers\Forum;

use HttpResponse;
use HttpRequest;
use Misuzu\Pagination;
use Misuzu\Forum\ForumTopic;
use Misuzu\Forum\ForumTopicNotFoundException;
use Misuzu\Users\User;

class ForumTopicHandler extends ForumHandler {
    public function topic(HttpResponse $response, HttpRequest $request, int $topicId) {
        try {
            $topicInfo = ForumTopic::byId($topicId);
        } catch(ForumTopicNotFoundException $ex) {
            return 404;
        }

        var_dump($topicInfo->getId());
    }

    public function reply(HttpResponse $response, HttpRequest $request, int $topicId) {
    }

    // Should support a since param to fetch a number of points after a point in time/post id
    public function live(HttpResponse $response, HttpRequest $request, int $topicId) {
        try {
            $topicInfo = ForumTopic::byId($topicId);
        } catch(ForumTopicNotFoundException $ex) {
            return 404;
        }

        if(!$topicInfo->getCategory()->canView(User::getCurrent()))
            return 403;

        $sincePostId = (int)($request->getQueryParam('since', FILTER_SANITIZE_NUMBER_INT) ?? -1);

        $ajaxInfo = [
            'id' => $topicInfo->getId(),
            'title' => $topicInfo->getTitle(),
        ];

        $categoryInfo = $topicInfo->getCategory();
        $ajaxInfo['category'] = [
            'id' => $categoryInfo->getId(),
            'name' => $categoryInfo->getName(),
            'tree' => [],
        ];
        
        $parentTree = $categoryInfo->getParentTree();
        foreach($parentTree as $parentInfo)
            $ajaxInfo['category']['tree'][] = [
                'id' => $parentInfo->getId(),
                'name' => $parentInfo->getName(),
            ];

        if($topicInfo->hasPriorityVoting()) {
            $ajaxInfo['priority'] = [
                'total' => $topicInfo->getPriority(),
                'votes' => [],
            ];
            $topicPriority = $topicInfo->getPriorityVotes();
            foreach($topicPriority as $priorityInfo) {
                $priorityUserInfo = $priorityInfo->getUser();
                $ajaxInfo['priority']['votes'][] = [
                    'count' => $priorityInfo->getPriority(),
                    'user' => [
                        'id' => $priorityUserInfo->getId(),
                        'name' => $priorityUserInfo->getUsername(),
                        'colour' => $priorityUserInfo->getColour()->getRaw(),
                    ],
                ];
            }
        }

        if($topicInfo->hasPoll()) {
            $pollInfo = $topicInfo->getPoll();
            $ajaxInfo['poll'] = [
                'id' => $pollInfo->getId(),
                'options' => [],
            ];

            $pollOptions = $pollInfo->getOptions();
            foreach($pollOptions as $optionInfo)
                $ajaxInfo['poll']['options'][] = [
                    'id' => $optionInfo->getId(),
                    'text' => $optionInfo->getText(),
                    'vote_count' => $optionInfo->getVotes(),
                    'vote_percent' => $optionInfo->getPercentage(),
                ];
        }

        if($sincePostId >= 0) {
            // Should contain all info necessary to build said posts
            // Maybe just serialised HTML a la YTKNS?
            $ajaxInfo['posts'] = [];
        }

        return $ajaxInfo;
    }

    public function delete(HttpResponse $response, HttpRequest $request, int $topicId) {
    }

    public function restore(HttpResponse $response, HttpRequest $request, int $topicId) {
    }

    public function nuke(HttpResponse $response, HttpRequest $request, int $topicId) {
    }

    public function bump(HttpResponse $response, HttpRequest $request, int $topicId) {
    }

    public function lock(HttpResponse $response, HttpRequest $request, int $topicId) {
    }

    public function legacy(HttpResponse $response, HttpRequest $request) {
        $postId  = (int)$request->getQueryParam('p', FILTER_SANITIZE_NUMBER_INT);
        if($postId > 0) {
            $response->redirect(url('forum-post', ['post' => $postId]));
            return;
        }

        $topicId = (int)$request->getQueryParam('t', FILTER_SANITIZE_NUMBER_INT);
        if($topicId > 0) {
            $response->redirect(url('forum-topic', ['topic' => $topicId]));
            return;
        }

        return 404;
    }
}
