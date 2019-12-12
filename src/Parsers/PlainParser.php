<?php
namespace Misuzu\Parsers;

class PlainParser implements ParserInterface {
    public function parseText(string $text): string {
        return nl2br($text);
    }

    public function parseLine(string $line): string {
        return $line;
    }
}
