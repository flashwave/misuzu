<?php
namespace Misuzu\Parsers;

use InvalidArgumentException;
use Misuzu\Parsers\BBCode\BBCodeParser;

final class Parser {
    public const PLAIN = 0;
    public const BBCODE = 1;
    public const MARKDOWN = 2;

    private const PARSERS = [
        self::PLAIN => PlainParser::class,
        self::BBCODE => BBCodeParser::class,
        self::MARKDOWN => MarkdownParser::class,
    ];
    public const NAMES = [
        self::PLAIN    => 'Plain text',
        self::BBCODE   => 'BB Code',
        self::MARKDOWN => 'Markdown',
    ];

    private static $instances = [];

    public static function isValid(int $parser): bool {
        return array_key_exists($parser, self::PARSERS);
    }

    public static function name(int $parser): string {
        return self::isValid($parser) ? self::NAMES[$parser] : '';
    }

    public static function instance(int $parser): ParserInterface {
        if(!self::isValid($parser))
            throw new InvalidArgumentException('Invalid parser.');

        if(!isset(self::$instances[$parser])) {
            $className = self::PARSERS[$parser];
            self::$instances[$parser] = new $className;
        }

        return self::$instances[$parser];
    }
}
