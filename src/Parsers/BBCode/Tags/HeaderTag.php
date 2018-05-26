<?php
namespace Misuzu\Parsers\BBCode\Tags;

use Misuzu\Parsers\BBCode\BBCodeSimpleTag;

final class HeaderTag extends BBCodeSimpleTag
{
    public function getPattern(): string
    {
        return "/\[header\](.*)\[\/header\]/";
    }

    public function getReplacement(): string
    {
        return '<h1>$1</h1>';
    }
}
