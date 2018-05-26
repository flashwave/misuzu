<?php
namespace Misuzu\Parsers\BBCode\Tags;

use Misuzu\Parsers\BBCode\BBCodeSimpleTag;

final class SpoilerTag extends BBCodeSimpleTag
{
    public function getPattern(): string
    {
        return "/\[spoiler\](.*)\[\/spoiler\]/";
    }

    public function getReplacement(): string
    {
        return '<span class="spoiler-class-name">$1</span>';
    }
}
