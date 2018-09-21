<?php
use Misuzu\Parsers\MarkdownParser;
use Misuzu\Parsers\BBCode\BBCodeParser;

define('MSZ_PARSER_PLAIN', 0);
define('MSZ_PARSER_BBCODE', 1);
define('MSZ_PARSER_MARKDOWN', 2);
define('MSZ_PARSERS', [
    MSZ_PARSER_PLAIN,
    MSZ_PARSER_BBCODE,
    MSZ_PARSER_MARKDOWN,
]);

define('MSZ_PARSERS_NAMES', [
    MSZ_PARSER_PLAIN => 'Plain text',
    MSZ_PARSER_BBCODE => 'BB Code',
    MSZ_PARSER_MARKDOWN => 'Markdown',
]);

function parser_is_valid(int $parser): bool
{
    return in_array($parser, MSZ_PARSERS, true);
}

function parser_name(int $parser): string
{
    return parser_is_valid($parser) ? MSZ_PARSERS_NAMES[$parser] : '';
}

function parse_text(string $text, int $parser): string
{
    if (!parser_is_valid($parser)) {
        return '';
    }

    switch ($parser) {
        case MSZ_PARSER_MARKDOWN:
            return MarkdownParser::instance()->parseText($text);

        case MSZ_PARSER_BBCODE:
            return BBCodeParser::instance()->parseText($text);

        case MSZ_PARSER_PLAIN:
            return $text;
    }
}

function parse_line(string $line, int $parser): string
{
    if (!parser_is_valid($parser)) {
        return '';
    }

    switch ($parser) {
        case MSZ_PARSER_MARKDOWN:
            return MarkdownParser::instance()->parseLine($line);

        case MSZ_PARSER_BBCODE:
            return BBCodeParser::instance()->parseLine($line);

        case MSZ_PARSER_PLAIN:
            return $line;
    }
}
