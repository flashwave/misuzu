<?php
namespace Misuzu\Parsers\BBCode\Tags;

use Misuzu\Parsers\BBCode\BBCodeTag;

final class AudioTag extends BBCodeTag
{
    public function parseText(string $text): string
    {
        return preg_replace_callback(
            '#\[audio\]((?:https?:\/\/).*)\[/audio\]#',
            function ($matches) {
                // todo: domain matches etc.
                // sites like soundcloud (and mixcloud, if possible) should be embeddable through this tag
                return "<audio controls src='{$matches[1]}'></audio>";
            },
            $text
        );
    }
}
