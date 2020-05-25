<?php
namespace Misuzu;

use Twig_Extension;
use Twig_Filter;
use Twig_Function;
use Misuzu\Parsers\Parser;

final class TwigMisuzu extends Twig_Extension {
    public function getFilters() {
        return [
            new Twig_Filter('html_colour', 'html_colour'),
            new Twig_Filter('country_name', 'get_country_name'),
            new Twig_Filter('byte_symbol', 'byte_symbol'),
            new Twig_Filter('html_link', 'html_link'),
            // deprecate this call, convert to html in php
            new Twig_Filter('parse_text', fn(string $text, int $parser): string => Parser::instance($parser)->parseText($text)),
            new Twig_Filter('perms_check', 'perms_check'),
            new Twig_Filter('bg_settings', 'user_background_settings_strings'),
            new Twig_Filter('clamp', 'clamp'),
            new Twig_Filter('as_platform', fn(string $userAgent) => (new \WhichBrowser\Parser($userAgent))->toString()),
        ];
    }

    public function getFunctions() {
        return [
            new Twig_Function('url_construct', 'url_construct'),
            new Twig_Function('warning_has_duration', 'user_warning_has_duration'),
            new Twig_Function('url', 'url'),
            new Twig_Function('url_list', 'url_list'),
            new Twig_Function('html_avatar', 'html_avatar'),
            new Twig_Function('forum_may_have_children', 'forum_may_have_children'),
            new Twig_Function('forum_may_have_topics', 'forum_may_have_topics'),
            new Twig_Function('forum_has_priority_voting', 'forum_has_priority_voting'),
            new Twig_Function('csrf_token', fn() => CSRF::token()),
            new Twig_Function('git_commit_hash', fn(bool $long = false) => GitInfo::hash($long)),
            new Twig_Function('git_tag', fn() => GitInfo::tag()),
            new Twig_Function('git_branch', fn() => GitInfo::branch()),
            new Twig_Function('startup_time', fn(float $time = MSZ_STARTUP) => microtime(true) - $time),
            new Twig_Function('sql_query_count', fn() => DB::queries()),
        ];
    }
}
