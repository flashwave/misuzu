<?php
namespace Misuzu\Http\Handlers\Forum;

use HttpResponse;
use HttpRequest;
use Misuzu\Forum\ForumPoll;
use Misuzu\Forum\ForumPollNotFoundException;
use Misuzu\Users\User;

class ForumPollHandler extends ForumHandler {
    public function vote(HttpResponse $response, HttpRequest $request, int $postId) {
        try {
            $pollInfo = ForumPoll::byId($pollId);
        } catch(ForumPollNotFoundException $ex) {
            return 404;
        }

        // check perms lol

        $results = [];

        foreach($pollInfo->getOptions() as $optionInfo)
            $results[] = [
                'id' => $optionInfo->getId(),
                'text' => $optionInfo->getText(),
                'vote_count' => $optionInfo->getVotes(),
                'vote_percent' => $optionInfo->getPercentage(),
            ];

        return $results;
    }
}
