<?php
namespace Misuzu\Parsers\BBCode;

abstract class BBCodeSimpleTag
{
    abstract public function getPattern(): string;
    abstract public function getReplacement(): string;

    public function parseText(string $text): string
    {
        return preg_replace($this->getPattern(), $this->getReplacement(), $text);
    }
}
