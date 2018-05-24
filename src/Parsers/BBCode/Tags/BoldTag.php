<?php
namespace Misuzu\Parsers\BBCode\Tags;

use Misuzu\Parsers\BBCode\BBCodeSimpleTag;

final class BoldTag extends BBCodeSimpleTag
{
    public function getPattern(): string
    {
        return "/\[b\](.*)\[\/b\]/";
    }

    public function getReplacement(): string
    {
        return '<b>$1</b>';
    }
}
