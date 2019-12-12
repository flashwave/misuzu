<?php
namespace Misuzu\Feeds;

use InvalidArgumentException;

class Feed {
    private string $title = '';
    private ?string $description = null;
    private ?int $lastUpdate = null;
    private ?string $contentUrl = null;
    private ?string $feedUrl = null;
    private array $feedItems = [];

    public function getTitle(): string {
        return $this->title;
    }
    public function setTitle(string $title): self {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): string {
        return $this->description ?? '';
    }
    public function hasDescription(): bool {
        return isset($this->description);
    }
    public function setDescription(?string $description): self {
        $this->description = $description;
        return $this;
    }

    public function getLastUpdate(): int {
        return $this->lastUpdate ?? 0;
    }
    public function hasLastUpdate(): bool {
        return isset($this->lastUpdate);
    }
    public function setLastUpdate(?int $lastUpdate): self {
        $this->lastUpdate = $lastUpdate;
        return $this;
    }

    public function getContentUrl(): string {
        return $this->contentUrl ?? '';
    }
    public function hasContentUrl(): bool {
        return isset($this->contentUrl);
    }
    public function setContentUrl(?string $contentUrl): self {
        $this->contentUrl = $contentUrl;
        return $this;
    }

    public function getFeedUrl(): string {
        return $this->feedUrl ?? '';
    }
    public function hasFeedUrl(): bool {
        return isset($this->feedUrl);
    }
    public function setFeedUrl(?string $feedUrl): self {
        $this->feedUrl = $feedUrl;
        return $this;
    }

    public function getItems(): array {
        return $this->feedItems;
    }
    public function hasItems(): bool {
        return count($this->feedItems) > 0;
    }
    public function addItem(FeedItem $item): self {
        if($item === null)
            throw new InvalidArgumentException('item may not be null');
        $this->feedItems[] = $item;
        return $this;
    }
}
