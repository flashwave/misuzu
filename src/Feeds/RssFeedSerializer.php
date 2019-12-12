<?php
namespace Misuzu\Feeds;

use DOMDocument;
use DOMElement;

class RssFeedSerializer extends XmlFeedSerializer {
    protected function formatTime(int $time): string {
        return date('r', $time);
    }

    protected function createRoot(DOMDocument $document, Feed $feed): DOMElement {
        $rss = $document->appendChild($document->createElement('rss'));
        $rss->setAttribute('version', '2.0');
        $rss->setAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');

        $channel = $rss->appendChild($document->createElement('channel'));
        $channel->appendChild($document->createElement('ttl', '900'));
        return $channel;
    }

    protected function createTitle(DOMDocument $document, string $title): DOMElement {
        return $document->createElement('title', $this->cleanString($title));
    }

    protected function createDescription(DOMDocument $document, string $description): ?DOMElement {
        return $document->createElement('description', $this->cleanString($description));
    }

    protected function createLastUpdate(DOMDocument $document, int $lastUpdate): ?DOMElement {
        return $document->createElement('pubDate', $this->formatTime($lastUpdate));
    }

    protected function createContentUrl(DOMDocument $document, string $contentUrl): ?DOMElement {
        return $document->createElement('link', $this->cleanString($contentUrl));
    }

    protected function createFeedUrl(DOMDocument $document, string $feedUrl): ?DOMElement {
        $link = $document->createElement('atom:link');
        $link->setAttribute('href', $this->cleanString($feedUrl));
        $link->setAttribute('ref', 'self');
        return $link;
    }

    protected function createItem(DOMDocument $document, FeedItem $feedItem): DOMElement {
        return $document->createElement('item');
    }

    protected function createItemTitle(DOMDocument $document, string $title): DOMElement {
        return $document->createElement('title', $this->cleanString($title));
    }

    protected function createItemSummary(DOMDocument $document, string $summary): ?DOMElement {
        return $document->createElement('description', $this->cleanString($summary));
    }

    protected function createItemContent(DOMDocument $document, string $content): ?DOMElement {
        return null;
    }

    protected function createItemCreationDate(DOMDocument $document, int $creationDate): ?DOMElement {
        return $document->createElement('pubDate', $this->formatTime($creationDate));
    }

    protected function createItemUniqueId(DOMDocument $document, string $uniqueId): ?DOMElement {
        $elem = $document->createElement('guid', $uniqueId);
        $elem->setAttribute('isPermaLink', 'true');
        return $elem;
    }

    protected function createItemContentUrl(DOMDocument $document, string $contentUrl): ?DOMElement {
        return $document->createElement('link', $contentUrl);
    }

    protected function createItemCommentsUrl(DOMDocument $document, string $commentsUrl): ?DOMElement {
        return $document->createElement('comments', $commentsUrl);
    }

    protected function createItemAuthor(DOMDocument $document, ?string $authorName, ?string $authorUrl): ?DOMElement {
        return null;
    }
}
