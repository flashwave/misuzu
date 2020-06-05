<?php
namespace Misuzu\Parsers\BBCode\Tags;

use Misuzu\Parsers\BBCode\BBCodeTag;

final class AlignTag extends BBCodeTag {
    public function parseText(string $text): string {
        return preg_replace_callback(
            '#\[align=(left|right|center|centre|justify)\](.+?)\[/align\]#',
            function ($matches) {
                if($matches[1] === 'centre')
                    $matches[1] = 'center';
                return sprintf('<div style="text-align: %s;">%s</div>', $matches[1], $matches[2]);
            },
            $text
        );
    }
}
