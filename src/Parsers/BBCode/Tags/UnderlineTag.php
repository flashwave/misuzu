<?php
namespace Misuzu\Parsers\BBCode\Tags;

use Misuzu\Parsers\BBCode\BBCodeSimpleTag;

final class UnderlineTag extends BBCodeSimpleTag {
    public function getPattern(): string {
        return "/\[u\](.+?)\[\/u\]/";
    }

    public function getReplacement(): string {
        return '<u>$1</u>';
    }
}
