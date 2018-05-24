<?php
namespace Misuzu\Parsers\BBCode;

abstract class BBCodeTag
{
    abstract public function parseText(string $text): string;
}
