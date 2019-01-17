<?php
namespace Misuzu\Parsers\BBCode\Tags;

use Misuzu\Parsers\BBCode\BBCodeTag;

final class LinkifyTag extends BBCodeTag
{
    public function parseText(string $text): string
    {
        return preg_replace_callback(
            '/(^|[\n ])([\w]*?)([\w]*?:\/\/[\w]+[^ \,\"\n\r\t<]*)/is',
            function ($matches) {
                $matches[0] = trim($matches[0]);
                $url = parse_url($matches[0]);

                if (empty($url['scheme']) || !in_array(mb_strtolower($url['scheme']), ['http', 'https', 'ftp'], true)) {
                    return $matches[0];
                }

                return sprintf('<a href="%1$s" class="link" target="_blank" rel="noreferrer noopener">%1$s</a>', $matches[0]);
            },
            $text
        );
    }
}
