<?php
namespace Misuzu\Feeds;

abstract class FeedSerializer {
    abstract public function serializeFeed(Feed $feed): string;
}
