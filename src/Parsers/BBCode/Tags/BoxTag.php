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
                $title = strlen($matches[1]) ? $matches[1] : 'Spoiler';
                // restyle this entirely
                return '<div class="container">'
                    . "<div class='container__title'>{$title}</div>"
                    . "<div class='container__content'>{$matches[2]}</div>"
                    . '</div>';
            },
            $text
        );
    }
}
