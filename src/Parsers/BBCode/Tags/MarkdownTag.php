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
                return sprintf('<div class="markdown">%s</div>', parse_text($matches[1], MSZ_PARSER_MARKDOWN));
            },
            $text
        );
    }
}
