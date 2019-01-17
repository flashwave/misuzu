<?php
namespace Misuzu\Parsers\BBCode\Tags;

use Misuzu\Parsers\BBCode\BBCodeTag;

final class VideoTag extends BBCodeTag
{
    private const YOUTUBE_URL_REGEX = '#^(?:www\.)?youtube(?:-nocookie)?\.(?:[a-z]{2,63})$#u';
    private const YOUTUBE_EMBED = '<iframe width="560" height="315" src="https://www.youtube-nocookie.com/embed/%s?rel=0" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';

    public function parseText(string $text): string
    {
        return preg_replace_callback(
            '#\[video\]((?:https?:\/\/).+?)\[/video\]#',
            function ($matches) {
                $url = parse_url($matches[1]);

                if (empty($url['scheme']) || !in_array(mb_strtolower($url['scheme']), ['http', 'https'], true)) {
                    return $matches[0];
                }

                $url['host'] = mb_strtolower($url['host']);

                // support youtube playlists?

                if ($url['host'] === 'youtu.be' || $url['host'] === 'www.youtu.be') {
                    return sprintf(self::YOUTUBE_EMBED, $url['path']);
                }

                if (!empty($url['query']) && ($url['path'] ?? '') === '/watch' && preg_match(self::YOUTUBE_URL_REGEX, $url['host'])) {
                    parse_str(html_entity_decode($url['query']), $ytQuery);

                    if (!empty($ytQuery['v']) && preg_match('#^([a-zA-Z0-9_-]+)$#u', $ytQuery['v'])) {
                        return sprintf(self::YOUTUBE_EMBED, $ytQuery['v']);
                    }
                }

                $mediaUrl = proxy_media_url($matches[1]);
                return sprintf('<video controls src="%s" style="max-width:100%%;max-height:100%%;"></video>', $mediaUrl);
            },
            $text
        );
    }
}
