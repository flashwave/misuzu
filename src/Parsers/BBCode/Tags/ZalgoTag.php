<?php
namespace Misuzu\Parsers\BBCode\Tags;

use Misuzu\Parsers\BBCode\BBCodeTag;

final class ZalgoTag extends BBCodeTag
{
    public function parseText(string $text): string
    {
        return preg_replace_callback(
            '#\[zalgo\](.+?)\[\/zalgo\]#s',
            function ($matches) {
                return zalgo_run($matches[1]);
            },
            $text
        );
    }
}
