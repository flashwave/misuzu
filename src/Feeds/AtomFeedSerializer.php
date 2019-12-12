<?php
namespace Misuzu\Feeds;

use DOMDocument;
use DOMElement;

class AtomFeedSerializer extends XmlFeedSerializer {
    protected function formatTime(int $time): string {
        return date('c', $time);
    }

    protected function createRoot(DOMDocument $document, Feed $feed): DOMElement {
        $atom = $document->appendChild($document->createElement('feed'));
        $atom->setAttribute('xmlns', 'http://www.w3.org/2005/Atom');

        $atom->appendChild(
            $document->createElement(
                'id',
                $feed->hasContentUrl()
                    ? $this->cleanString($feed->getContentUrl())
                    : time()
            )
        );

        return $atom;
    }

    protected function createTitle(DOMDocument $document, string $title): DOMElement {
        return $document->createElement('title', $this->cleanString($title));
    }

    protected function createDescription(DOMDocument $document, string $description): ?DOMElement {
        return $document->createElement('subtitle', $this->cleanString($description));
    }

    protected function createLastUpdate(DOMDocument $document, int $lastUpdate): ?DOMElement {
        return $document->createElement('updated', $this->formatTime($lastUpdate));
    }

    protected function createContentUrl(DOMDocument $document, string $contentUrl): ?DOMElement {
        $link = $document->createElement('link');
        $link->setAttribute('href', $this->cleanString($contentUrl));
        return $link;
    }

    protected function createFeedUrl(DOMDocument $document, string $feedUrl): ?DOMElement {
        $link = $document->createElement('link');
        $link->setAttribute('href', $this->cleanString($feedUrl));
        $link->setAttribute('ref', 'self');
        return $link;
    }

    protected function createItem(DOMDocument $document, FeedItem $feedItem): DOMElement {
        $elem = $document->createElement('entry');

        $elem->appendChild(
            $document->createElement(
                'id',
                $feedItem->hasContentUrl()
                    ? $this->cleanString($feedItem->getContentUrl())
                    : time()
            )
        );

        return $elem;
    }

    protected function createItemTitle(DOMDocument $document, string $title): DOMElement {
        return $document->createElement('title', $this->cleanString($title));
    }

    protected function createItemSummary(DOMDocument $document, string $summary): ?DOMElement {
        return $document->createElement('summary', $this->cleanString($summary));
    }

    protected function createItemContent(DOMDocument $document, string $content): ?DOMElement {
        $elem = $document->createElement('content', $this->cleanString($content));
        $elem->setAttribute('type', 'html');
        return $elem;
    }

    protected function createItemCreationDate(DOMDocument $document, int $creationDate): ?DOMElement {
        return $document->createElement('updated', $this->formatTime($creationDate));
    }

    protected function createItemUniqueId(DOMDocument $document, string $uniqueId): ?DOMElement {
        return null;
    }

    protected function createItemContentUrl(DOMDocument $document, string $contentUrl): ?DOMElement {
        $elem = $document->createElement('link');
        $elem->setAttribute('href', $this->cleanString($contentUrl));
        $elem->setAttribute('type', 'text/html');
        return $elem;
    }

    protected function createItemCommentsUrl(DOMDocument $document, string $commentsUrl): ?DOMElement {
        return null;
    }

    protected function createItemAuthor(DOMDocument $document, ?string $authorName, ?string $authorUrl): ?DOMElement {
        if(empty($authorName) && empty($authorUrl))
            return null;

        $elem = $document->createElement('author');

        if(!empty($authorName))
            $elem->appendChild($document->createElement('name', $this->cleanString($authorName)));

        if(!empty($authorUrl))
            $elem->appendChild($document->createElement('uri', $this->cleanString($authorUrl)));

        return $elem;
    }
}
