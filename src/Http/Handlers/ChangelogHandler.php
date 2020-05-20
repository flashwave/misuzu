<?php
namespace Misuzu\Http\Handlers;

use ErrorException;
use HttpResponse;
use HttpRequest;
use Misuzu\Config;
use Misuzu\Pagination;
use Misuzu\Changelog\ChangelogChange;
use Misuzu\Changelog\ChangelogChangeNotFoundException;
use Misuzu\Changelog\ChangelogTag;
use Misuzu\Changelog\ChangelogTagNotFoundException;
use Misuzu\Feeds\Feed;
use Misuzu\Feeds\FeedItem;
use Misuzu\Feeds\AtomFeedSerializer;
use Misuzu\Feeds\RssFeedSerializer;
use Misuzu\Users\User;
use Misuzu\Users\UserNotFoundException;

class ChangelogHandler extends Handler {
    public function index(HttpResponse $response, HttpRequest $request) {
        $filterDate = $request->getQueryParam('date', FILTER_SANITIZE_STRING);
        $filterUser = $request->getQueryParam('user', FILTER_SANITIZE_NUMBER_INT);
        //$filterTags = $request->getQueryParam('tags');

        if($filterDate !== null)
            try {
                $dateParts = explode('-', $filterDate, 3);
                $filterDate = gmmktime(12, 0, 0, $dateParts[1], $dateParts[2], $dateParts[0]);
            } catch(ErrorException $ex) {
                return 404;
            }

        if($filterUser !== null)
            try {
                $filterUser = User::byId($filterUser);
            } catch(UserNotFoundException $ex) {
                return 404;
            }

        /*if($filterTags !== null) {
            $splitTags = explode(',', $filterTags);
            $filterTags = [];
            for($i = 0; $i < min(10, count($splitTags)); ++$i)
                try {
                    $filterTags[] = ChangelogTag::byId($splitTags[$i]);
                } catch(ChangelogTagNotFoundException $ex) {
                    return 404;
                }
        }*/

        $count = $filterDate !== null ? -1 : ChangelogChange::countAll($filterDate, $filterUser);
        $pagination = new Pagination($count, 30);
        if(!$pagination->hasValidOffset())
            return 404;

        $changes = ChangelogChange::all($pagination, $filterDate, $filterUser);
        if(empty($changes))
            return 404;

        $response->setTemplate('changelog.index', [
            'changelog_infos' => $changes,
            'changelog_date' => $filterDate,
            'changelog_user' => $filterUser,
            'changelog_pagination' => $pagination,
            'comments_user' => User::getCurrent(),
        ]);
    }

    public function change(HttpResponse $response, HttpRequest $request, int $changeId) {
        try {
            $changeInfo = ChangelogChange::byId($changeId);
        } catch(ChangelogChangeNotFoundException $ex) {
            return 404;
        }

        $response->setTemplate('changelog.change', [
            'change_info' => $changeInfo,
            'comments_user' => User::getCurrent(),
        ]);
    }

    private function createFeed(string $feedMode): Feed {
        $changes = ChangelogChange::all(new Pagination(10));

        $feed = (new Feed)
            ->setTitle(Config::get('site.name', Config::TYPE_STR, 'Misuzu') . ' » Changelog')
            ->setDescription('Live feed of changes to ' . Config::get('site.name', Config::TYPE_STR, 'Misuzu') . '.')
            ->setContentUrl(url_prefix(false) . url('changelog-index'))
            ->setFeedUrl(url_prefix(false) . url("changelog-feed-{$feedMode}"));

        foreach($changes as $change) {
            $changeUrl = url_prefix(false) . url('changelog-change', ['change' => $change->getId()]);
            $commentsUrl = url_prefix(false) . url('changelog-change-comments', ['change' => $change->getId()]);

            $feedItem = (new FeedItem)
                ->setTitle($change->getActionString() . ': ' . $change->getHeader())
                ->setCreationDate($change->getCreatedTime())
                ->setUniqueId($changeUrl)
                ->setContentUrl($changeUrl)
                ->setCommentsUrl($commentsUrl);

            $feed->addItem($feedItem);
        }

        return $feed;
    }

    public function feedAtom(HttpResponse $response, HttpRequest $request) {
        $response->setContentType('application/atom+xml; charset=utf-8');
        return (new AtomFeedSerializer)->serializeFeed(self::createFeed('atom'));
    }

    public function feedRss(HttpResponse $response, HttpRequest $request) {
        $response->setContentType('application/rss+xml; charset=utf-8');
        return (new RssFeedSerializer)->serializeFeed(self::createFeed('rss'));
    }

    public function legacy(HttpResponse $response, HttpRequest $request) {
        $changeId = $request->getQueryParam('c', FILTER_SANITIZE_NUMBER_INT);
        if($changeId) {
            $response->redirect(url('changelog-change', ['change' => $changeId]), true);
            return;
        }

        $response->redirect(url('changelog-index', [
            'date' => $request->getQueryParam('d', FILTER_SANITIZE_STRING),
            'user' => $request->getQueryParam('u', FILTER_SANITIZE_NUMBER_INT),
        ]), true);
    }
}
