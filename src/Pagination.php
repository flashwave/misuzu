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

    private const PAGE_RANGE = 5;

    public function render(string $pathOrName, array $params = [], string $pageParam = self::DEFAULT_PARAM, string $urlFragment = ''): string {
        if($this->getPages() <= 1)
            return '';

        if($pathOrName[0] !== '/')
            $pathOrName = url($pathOrName);

        $getUrl = function(int $page) use ($pathOrName, $params, $pageParam, $urlFragment) {
            if($page <= 1)
                unset($params[$pageParam]);
            else
                $params[$pageParam] = $page;

            $url = $pathOrName;
            if(!empty($params))
                $url .= '?' . http_build_query($params);
            if(!empty($urlFragment))
                $url .= '#' . rawurldecode($urlFragment);

            return $url;
        };

        $html = '<div class="pagination">';

        $html .= '<div class="pagination__section pagination__section--first">';
        if($this->getPage() <= 1) {
            $html .= '<div class="pagination__link pagination__link--first pagination__link--disabled"><i class="fas fa-angle-double-left"></i></div>';
            $html .= '<div class="pagination__link pagination__link--prev pagination__link--disabled"><i class="fas fa-angle-left"></i></div>';
        } else {
            $html .= '<a href="'  . $getUrl(1) . '" class="pagination__link pagination__link--first" rel="first"><i class="fas fa-angle-double-left"></i></a>';
            $html .= '<a href="'  . $getUrl($this->getPage() - 1) . '" class="pagination__link pagination__link--prev" rel="prev"><i class="fas fa-angle-left"></i></a>';
        }
        $html .= '</div>';

        $html .= '<div class="pagination__section pagination__section--pages">';
        $start = max($this->getPage() - self::PAGE_RANGE, 1);
        $stop = min($this->getPage() + self::PAGE_RANGE, $this->getPages());
        for($i = $start; $i <= $stop; ++$i)
            $html .= '<a href="' . $getUrl($i) . '" class="pagination__link' . ($i === $this->getPage() ? ' pagination__link--current' : '') . '">' . number_format($i) . '</a>';
        $html .= '</div>';

        $html .= '<div class="pagination__section pagination__section--last">';
        if($this->getPage() >= $this->getPages()) {
            $html .= '<div class="pagination__link pagination__link--next pagination__link--disabled"><i class="fas fa-angle-right"></i></div>';
            $html .= '<div class="pagination__link pagination__link--last pagination__link--disabled"><i class="fas fa-angle-double-right"></i></div>';
        } else {
            $html .= '<a href="'  . $getUrl($this->getPage() + 1) . '" class="pagination__link pagination__link--next" rel="next"><i class="fas fa-angle-right"></i></a>';
            $html .= '<a href="'  . $getUrl($this->getPages()) . '" class="pagination__link pagination__link--last" rel="last"><i class="fas fa-angle-double-right"></i></a>';
        }
        $html .= '</div>';

        return $html . '</div>';
    }
}
