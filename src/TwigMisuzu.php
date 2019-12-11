<?php
namespace Misuzu;

use Twig_Extension;
use Twig_Filter;
use Twig_Function;

final class TwigMisuzu extends Twig_Extension {
    public function getFilters() {
        return [
            new Twig_Filter('html_colour', 'html_colour'),
            new Twig_Filter('country_name', 'get_country_name'),
            new Twig_Filter('first_paragraph', 'first_paragraph'),
            new Twig_Filter('byte_symbol', 'byte_symbol'),
            new Twig_Filter('html_link', 'html_link'),
            new Twig_Filter('parse_line', 'parse_line'),
            new Twig_Filter('parse_text', 'parse_text'),
            new Twig_Filter('asset_url', [static::class, 'assetUrl']),
            new Twig_Filter('perms_check', 'perms_check'),
            new Twig_Filter('bg_settings', 'user_background_settings_strings'),
            new Twig_Filter('colour_contrast', 'colour_get_css_contrast'),
            new Twig_Filter('colour_props', 'colour_get_properties'),
            new Twig_Filter('colour_hex', 'colour_get_hex'),
            new Twig_Filter('colour_inherit', 'colour_get_inherit'),
            new Twig_Filter('clamp', 'clamp'),
            new Twig_Filter('log_format', function (string $text, string $json): string {
                return vsprintf($text, json_decode($json));
            }),
        ];
    }

    public function getFunctions() {
        return [
            new Twig_Function('get_browser', 'get_browser'),
            new Twig_Function('url_construct', 'url_construct'),
            new Twig_Function('warning_has_duration', 'user_warning_has_duration'),
            new Twig_Function('url', 'url'),
            new Twig_Function('url_list', 'url_list'),
            new Twig_Function('html_tag', 'html_tag'),
            new Twig_Function('html_avatar', 'html_avatar'),
            new Twig_Function('changelog_action_name', 'changelog_action_name'),
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

    public static function assetUrl(string $path): string {
        $realPath = realpath(MSZ_ROOT . '/public/' . $path);

        if($realPath === false || !file_exists($realPath)) {
            return $path;
        }

        return $path . '?' . filemtime($realPath);
    }
}
