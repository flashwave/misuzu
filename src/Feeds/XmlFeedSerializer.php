<?php
namespace Misuzu\Feeds;

use DOMDocument;
use DOMElement;

abstract class XmlFeedSerializer extends FeedSerializer {
    public function serializeFeed(Feed $feed): string {
        $document = new DOMDocument('1.0', 'utf-8');
        $root = $this->createRoot($document, $feed);
        $root->appendChild($this->createTitle($document, $feed->getTitle()));

        if($feed->hasDescription())
            self::appendChild($root, $this->createDescription($document, $feed->getDescription()));
        if($feed->hasLastUpdate())
            self::appendChild($root, $this->createLastUpdate($document, $feed->getLastUpdate()));
        if($feed->hasContentUrl())
            self::appendChild($root, $this->createContentUrl($document, $feed->getContentUrl()));
        if($feed->hasFeedUrl())
            self::appendChild($root, $this->createFeedUrl($document, $feed->getFeedUrl()));

        if($feed->hasItems()) {
            foreach($feed->getItems() as $item) {
                $root->appendChild($this->serializeFeedItem($document, $item));
            }
        }

        return $document->saveXML();
    }

    private function serializeFeedItem(DOMDocument $document, FeedItem $feedItem): DOMElement {
        $elem = $this->createItem($document, $feedItem);
        $elem->appendChild($this->createItemTitle($document, $feedItem->getTitle()));

        if($feedItem->hasSummary())
            self::appendChild($elem, $this->createItemSummary($document, $feedItem->getSummary()));
        if($feedItem->hasContent())
            self::appendChild($elem, $this->createItemContent($document, $feedItem->getContent()));
        if($feedItem->hasCreationDate())
            self::appendChild($elem, $this->createItemCreationDate($document, $feedItem->getCreationDate()));
        if($feedItem->hasUniqueId())
            self::appendChild($elem, $this->createItemUniqueId($document, $feedItem->getUniqueId()));
        if($feedItem->hasContentUrl())
            self::appendChild($elem, $this->createItemContentUrl($document, $feedItem->getContentUrl()));
        if($feedItem->hasCommentsUrl())
            self::appendChild($elem, $this->createItemCommentsUrl($document, $feedItem->getCommentsUrl()));
        if($feedItem->hasAuthorName() || $feedItem->hasAuthorUrl())
            self::appendChild($elem, $this->createItemAuthor($document, $feedItem->getAuthorName(), $feedItem->getAuthorUrl()));

        return $elem;
    }

    protected function cleanString(string $string): string {
        return htmlspecialchars($string, ENT_XML1 | ENT_COMPAT | ENT_SUBSTITUTE);
    }

    protected static function appendChild(DOMElement $parent, ?DOMElement $elem): ?DOMElement {
        if($elem !== null)
            return $parent->appendChild($elem);
        return $elem;
    }

    abstract protected function formatTime(int $time): string;
    abstract protected function createRoot(DOMDocument $document, Feed $feed): DOMElement;
    abstract protected function createTitle(DOMDocument $document, string $title): DOMElement;
    abstract protected function createDescription(DOMDocument $document, string $description): ?DOMElement;
    abstract protected function createLastUpdate(DOMDocument $document, int $lastUpdate): ?DOMElement;
    abstract protected function createContentUrl(DOMDocument $document, string $contentUrl): ?DOMElement;
    abstract protected function createFeedUrl(DOMDocument $document, string $feedUrl): ?DOMElement;
    abstract protected function createItem(DOMDocument $document, FeedItem $feedItem): DOMElement;
    abstract protected function createItemTitle(DOMDocument $document, string $title): DOMElement;
    abstract protected function createItemSummary(DOMDocument $document, string $summary): ?DOMElement;
    abstract protected function createItemContent(DOMDocument $document, string $content): ?DOMElement;
    abstract protected function createItemCreationDate(DOMDocument $document, int $creationDate): ?DOMElement;
    abstract protected function createItemUniqueId(DOMDocument $document, string $uniqueId): ?DOMElement;
    abstract protected function createItemContentUrl(DOMDocument $document, string $contentUrl): ?DOMElement;
    abstract protected function createItemCommentsUrl(DOMDocument $document, string $commentsUrl): ?DOMElement;
    abstract protected function createItemAuthor(DOMDocument $document, ?string $authorName, ?string $authorUrl): ?DOMElement;
}
