<?php
namespace Misuzu\Feeds;

class FeedItem {
    private string $title = '';
    private ?string $summary = null;
    private ?string $content = null;
    private ?int $creationDate = null;
    private ?string $uniqueId = null;
    private ?string $contentUrl = null;
    private ?string $commentsUrl = null;
    private ?string $authorName = null;
    private ?string $authorUrl = null;

    public function getTitle(): string {
        return $this->title;
    }
    public function setTitle(string $title): self {
        $this->title = $title;
        return $this;
    }

    public function getSummary(): string {
        return $this->summary ?? '';
    }
    public function hasSummary(): bool {
        return isset($this->summary);
    }
    public function setSummary(?string $summary): self {
        $this->summary = $summary;
        return $this;
    }

    public function getContent(): string {
        return $this->content ?? '';
    }
    public function hasContent(): bool {
        return isset($this->content);
    }
    public function setContent(?string $content): self {
        $this->content = $content;
        return $this;
    }

    public function getCreationDate(): int {
        return $this->creationDate;
    }
    public function hasCreationDate(): bool {
        return isset($this->creationDate);
    }
    public function setCreationDate(?int $creationDate): self {
        $this->creationDate = $creationDate;
        return $this;
    }

    public function getUniqueId(): string {
        return $this->uniqueId ?? '';
    }
    public function hasUniqueId(): bool {
        return isset($this->uniqueId);
    }
    public function setUniqueId(?string $uniqueId): self {
        $this->uniqueId = $uniqueId;
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

    public function getCommentsUrl(): string {
        return $this->commentsUrl ?? '';
    }
    public function hasCommentsUrl(): bool {
        return isset($this->commentsUrl);
    }
    public function setCommentsUrl(?string $commentsUrl): self {
        $this->commentsUrl = $commentsUrl;
        return $this;
    }

    public function getAuthorName(): string {
        return $this->authorName ?? '';
    }
    public function hasAuthorName(): bool {
        return isset($this->authorName);
    }
    public function setAuthorName(?string $authorName): self {
        $this->authorName = $authorName;
        return $this;
    }

    public function getAuthorUrl(): string {
        return $this->authorUrl ?? '';
    }
    public function hasAuthorUrl(): bool {
        return isset($this->authorUrl);
    }
    public function setAuthorUrl(?string $authorUrl): self {
        $this->authorUrl = $authorUrl;
        return $this;
    }
}
