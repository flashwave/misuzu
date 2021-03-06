<?php
namespace Misuzu\Parsers\BBCode;

use Misuzu\Parsers\ParserInterface;

class BBCodeParser implements ParserInterface {
    private $tags = [];

    public function __construct(array $tags = []) {
        if(empty($tags)) {
            $tags = [
                // Advanced markup
                new Tags\CodeTag,
                new Tags\QuoteTag,
                new Tags\AlignTag,

                // Slightly more advanced markup
                new Tags\AudioTag,
                new Tags\VideoTag,

                // Basic markup
                new Tags\BoldTag,
                new Tags\ItalicsTag,
                new Tags\UnderlineTag,
                new Tags\StrikeTag,
                new Tags\ImageTag,
                new Tags\ZalgoTag,

                // Links
                new Tags\NamedUrlTag,
                new Tags\UrlTag,
                new Tags\LinkifyTag,

                // Finally parse leftover newlines
                new Tags\NewLineTag,
            ];
        }

        $this->tags = $tags;
    }

    public function parseText(string $text): string {
        foreach($this->tags as $tag)
            $text = $tag->parseText($text);
        return $text;
    }

    public function parseLine(string $line): string {
        return $this->parseText($line);
    }
}
