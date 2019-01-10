<?php
namespace Misuzu\Parsers\BBCode\Tags;

use Misuzu\Parsers\BBCode\BBCodeSimpleTag;

final class StrikeTag extends BBCodeSimpleTag
{
    public function getPattern(): string
    {
        return "/\[s\](.+?)\[\/s\]/";
    }

    public function getReplacement(): string
    {
        return '<del>$1</del>';
    }
}
