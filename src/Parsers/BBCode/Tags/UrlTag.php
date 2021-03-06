<?php
namespace Misuzu\Parsers\BBCode\Tags;

use Misuzu\Parsers\BBCode\BBCodeSimpleTag;

final class UrlTag extends BBCodeSimpleTag {
    public function getPattern(): string {
        return "/\[url\](.+?)\[\/url\]/s";
    }

    public function getReplacement(): string {
        return '<a href="$1" class="link" target="_blank" rel="noreferrer noopener">$1</a>';
    }
}
