<?php
namespace Misuzu\Parsers\BBCode\Tags;

use Misuzu\Parsers\BBCode\BBCodeTag;

final class CodeTag extends BBCodeTag
{
    public function parseText(string $text): string
    {
        return preg_replace_callback(
            '/\[code(?:\=([a-z]+))?\](.*?)\[\/code\]/s',
            function ($matches) {
                $class = strlen($matches[1]) ? "lang-{$matches[1]}" : '';
                $text = str_replace(['[', ']', '<', '>'], ['&#91;', '&#93;', '&lt;', '&gt;'], $matches[2]);
                return "<pre class='bbcode__code'><code class='bbcode__code-container {$class}'>{$text}</code></pre>";
            },
            $text
        );
    }
}
