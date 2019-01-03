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

    protected function inlineImage($excerpt)
    {
        $object = parent::inlineImage($excerpt);
        $object['element']['attributes']['src'] = proxy_media_url($object['element']['attributes']['src']);
        return $object;
    }
}
