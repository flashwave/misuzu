<?php
namespace Misuzu\Parsers\BBCode\Tags;

use Misuzu\Parsers\BBCode\BBCodeTag;

final class QuoteTag extends BBCodeTag {
    public function parseText(string $text): string {
        return preg_replace_callback(
            '#\[quote(?:=(.+?))?\](.+?)\[/quote\]#',
            function ($matches) {
                $prefix = '';

                if(!empty($matches[1])) {
                    $prefix = "<small>{$matches[1]}:</small>";
                }

                return "<blockquote>{$prefix}{$matches[2]}</blockquote>";
            },
            $text
        );
    }
}
