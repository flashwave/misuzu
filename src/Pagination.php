<?php
namespace Misuzu;

final class Pagination {
    private const INVALID_OFFSET = -1;
    private const START_PAGE = 1;
    public const DEFAULT_PARAM = 'p';

    private int $count = 0;
    private int $range = 0;
    private int $offset = 0;

    public function __construct(int $count, int $range = -1, ?string $readParam = self::DEFAULT_PARAM) {
        $this->count = max(0, $count);
        $this->range = $range < 0 ? $count : $range;

        if(!empty($readParam))
            $this->readPage($readParam);
    }

    public function getCount(): int {
        return $this->count;
    }

    public function getRange(): int {
        return $this->range;
    }

    public function getPages(): int {
        return ceil($this->getCount() / $this->getRange());
    }

    public function hasValidOffset(): bool {
        return $this->offset !== self::INVALID_OFFSET;
    }

    public function getOffset(): int {
        return $this->hasValidOffset() ? $this->offset : 0;
    }

    public function setOffset(int $offset): self {
        if($offset < 0)
            $offset = self::INVALID_OFFSET;

        $this->offset = $offset;
        return $this;
    }

    public function getPage(): int {
        if($this->getOffset() < 1)
            return self::START_PAGE;

        return floor($this->getOffset() / $this->getRange()) + self::START_PAGE;
    }

    public function setPage(int $page, bool $zeroBased = false): self {
        if(!$zeroBased)
            $page -= self::START_PAGE;

        $this->setOffset($this->getRange() * $page);
        return $this;
    }

    public function readPage(string $name = self::DEFAULT_PARAM, int $default = self::START_PAGE, ?array $source = null): self {
        $this->setPage(self::param($name, $default, $source));
        return $this;
    }

    public static function param(string $name = self::DEFAULT_PARAM, int $default = self::START_PAGE, ?array $source = null): int {
        $source ??= $_GET;

        if(isset($source[$name]) && is_string($source[$name]) && ctype_digit($source[$name]))
            return $source[$name];

        return $default;
    }
}
