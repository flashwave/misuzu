<?php
namespace Misuzu\Parsers;

use Parsedown;

class MarkdownParser extends Parsedown implements ParserInterface
{
    public function parseText(string $text): string
    {
        return $this->text($text);
    }

    public function parseLine(string $line): string
    {
        return $this->line($line);
    }
}
