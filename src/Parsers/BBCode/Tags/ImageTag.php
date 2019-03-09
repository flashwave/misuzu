<?php
namespace Misuzu\Parsers\BBCode\Tags;

use Misuzu\Parsers\BBCode\BBCodeTag;

final class ImageTag extends BBCodeTag
{
    public function parseText(string $text): string
    {
        return preg_replace_callback("/\[img\]((?:https?:\/\/).+?)\[\/img\]/", function ($matches) {
            $mediaUrl = proxy_media_url($matches[1]);
            return sprintf('<img src="%s" alt="%s" style="max-width:100%%;max-height:900px;">', $mediaUrl, $matches[1]);
        }, $text);
    }
}
