<?php
namespace Misuzu\Parsers\BBCode\Tags;

use Misuzu\Parsers\BBCode\BBCodeSimpleTag;

final class ImageTag extends BBCodeSimpleTag
{
    public function getPattern(): string
    {
        return "/\[img\]((?:https?:\/\/).*)\[\/img\]/";
    }

    public function getReplacement(): string
    {
        return '<a href="$1" target="_blank" rel="noreferrer noopener"><img src="$1" alt="$1" style="max-width:100%;max-height:100%;"></a>';
    }
}
