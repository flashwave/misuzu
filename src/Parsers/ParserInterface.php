<?php
namespace Misuzu\Parsers;

interface ParserInterface
{
    public function parseText(string $text): string;
    public function parseLine(string $line): string;
}
