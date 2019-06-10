<?php
namespace Misuzu\Parsers\BBCode\Tags;

use Misuzu\Parsers\BBCode\BBCodeSimpleTag;

final class ItalicsTag extends BBCodeSimpleTag {
    public function getPattern(): string {
        return "/\[i\](.+?)\[\/i\]/";
    }

    public function getReplacement(): string {
        return '<i>$1</i>';
    }
}
