<?php
namespace Misuzu\Parsers;

use Parsedown;

final class MarkdownParser extends Parser
{
    private $parsedown;

    public function __construct()
    {
        parent::__construct();
        $this->parsedown = new Parsedown;
    }

    public function parseText(string $text): string
    {
        return $this->parsedown->text($text);
    }
}
