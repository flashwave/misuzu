<?php
namespace Misuzu\Parsers\BBCode\Tags;

use Misuzu\Parsers\MarkdownParser;
use Misuzu\Parsers\BBCode\BBCodeTag;

final class MarkdownTag extends BBCodeTag
{
    public function parseText(string $text): string
    {
        return preg_replace_callback(
            '#\[md\](.*?)\[/md\]#s',
            function ($matches) {
                return MarkdownParser::getOrCreateInstance()->parseText($matches[1]);
            },
            $text
        );
    }
}
