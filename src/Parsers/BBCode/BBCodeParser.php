<?php
namespace Misuzu\Parsers\BBCode;

use Misuzu\Parsers\Parser;

final class BBCodeParser extends Parser
{
    private $tags = [];

    public function __construct(array $tags = [])
    {
        parent::__construct();

        if (empty($tags)) {
            $tags = [
                // Advanced markup
                new Tags\CodeTag,
                new Tags\QuoteTag,
                new Tags\BoxTag,
                new Tags\MarkdownTag,

                // Slightly more advanced markup
                new Tags\AudioTag,
                new Tags\VideoTag,

                // Basic markup
                new Tags\BoldTag,
                new Tags\ItalicsTag,
                new Tags\UnderlineTag,
                new Tags\StrikeTag,
                new Tags\SpoilerTag,
                new Tags\HeaderTag,
                new Tags\ImageTag,

                // Finally parse leftover newlines
                new Tags\NewLineTag,
            ];
        }

        $this->tags = $tags;
    }

    public function parseText(string $text): string
    {
        foreach ($this->tags as $tag) {
            $text = $tag->parseText($text);
        }

        return $text;
    }
}
