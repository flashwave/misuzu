<?php
namespace Misuzu\Parsers\BBCode\Tags;

use Misuzu\Parsers\BBCode\BBCodeSimpleTag;

final class NewLineTag extends BBCodeSimpleTag
{
    public function getPattern(): string
    {
        return "/\r\n|\r|\n/";
    }

    public function getReplacement(): string
    {
        return '<br>';
    }
}
