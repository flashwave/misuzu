<?php
namespace Misuzu\Parsers\BBCode\Tags;

use Misuzu\Parsers\BBCode\BBCodeTag;

final class AudioTag extends BBCodeTag {
    public function parseText(string $text): string {
        return preg_replace_callback(
            '#\[audio\]((?:https?:\/\/).+?)\[/audio\]#',
            function ($matches) {
                $url = parse_url($matches[1]);

                if(empty($url['scheme']) || !in_array(mb_strtolower($url['scheme']), ['http', 'https'], true)) {
                    return $matches[0];
                }

                //$url['host'] = mb_strtolower($url['host']);

                $mediaUrl = url_proxy_media($matches[1]);
                return "<audio controls src='{$mediaUrl}'></audio>";
            },
            $text
        );
    }
}
