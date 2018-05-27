<?php
namespace Misuzu\Parsers\BBCode\Tags;

use Misuzu\Parsers\BBCode\BBCodeTag;

final class BoxTag extends BBCodeTag
{
    public function parseText(string $text): string
    {
        return preg_replace_callback(
            '/\[box(?:=(.*))?\](.*)\[\/box\]/',
            function ($matches) {
                $randomId = 'toggle_' . bin2hex(random_bytes(8));
                $title = strlen($matches[1]) ? $matches[1] : 'Spoiler';
                return '<div class="container container--hidden" id="' . $randomId . '">'
                    . "<div class='container__title' onclick='toggleContainer(\"{$randomId}\")'>{$title}</div>"
                    . "<div class='container__content'>{$matches[2]}</div>"
                    . '</div>';
            },
            $text
        );
    }
}
