<?php
namespace Misuzu\Parsers\BBCode\Tags;

use Misuzu\Parsers\BBCode\BBCodeTag;

final class AudioTag extends BBCodeTag
{
    public function parseText(string $text): string
    {
        return preg_replace_callback(
            '#\[audio\]((?:https?:\/\/).+?)\[/audio\]#',
            function ($matches) {
                $mediaUrl = proxy_media_url($matches[1]);
                return "<audio controls src='{$mediaUrl}'></audio>";
            },
            $text
        );
    }
}
