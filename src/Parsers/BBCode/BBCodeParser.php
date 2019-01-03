<?php
namespace Misuzu\Parsers\BBCode;

use Misuzu\Parsers\ParserInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class BBCodeParser implements ParserInterface
{
    private static $instance;

    public static function instance(): BBCodeParser
    {
        return is_null(static::$instance) ? (static::$instance = new BBCodeParser()) : static::$instance;
    }

    private $tags = [];

    public function __construct(array $tags = [])
    {
        if (empty($tags)) {
            $tags = [
                // Advanced markup
                new Tags\CodeTag,
                new Tags\QuoteTag,

                // Slightly more advanced markup
                new Tags\AudioTag,
                new Tags\VideoTag,

                // Basic markup
                new Tags\BoldTag,
                new Tags\ItalicsTag,
                new Tags\UnderlineTag,
                new Tags\StrikeTag,
                new Tags\ImageTag,

                // Links
                new Tags\NamedUrlTag,
                new Tags\UrlTag,

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

    public function parseLine(string $line): string
    {
        return $this->parseText($line);
    }
}
