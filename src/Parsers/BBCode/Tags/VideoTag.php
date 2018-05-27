<?php
namespace Misuzu\Parsers\BBCode\Tags;

use Misuzu\Parsers\BBCode\BBCodeTag;

final class VideoTag extends BBCodeTag
{
    private const YOUTUBE_REGEX = '#^https?://(?:www\.)?youtu(?:be\.(?:[a-z]{2,63})|\.be|\be-nocookie\.com)/(?:.*?)v=([a-zA-Z0-9_-]+)#si';

    public function parseText(string $text): string
    {
        return preg_replace_callback(
            '#\[video\]((?:https?:\/\/).*)\[/video\]#',
            function ($matches) {
                if (preg_match(self::YOUTUBE_REGEX, $matches[1], $ytMatches)) {
                    return '<iframe width="560" height="315" src="https://www.youtube-nocookie.com/embed/'
                        . $ytMatches[1]
                        . '?rel=0" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>';
                }

                return "<video controls src='{$matches[1]}'></video>";
            },
            $text
        );
    }
}
