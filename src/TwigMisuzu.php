<?php
namespace Misuzu;

use Twig_Extension;
use Twig_Filter;
use Twig_Function;

final class TwigMisuzu extends Twig_Extension
{
    public function getFilters()
    {
        return [
            new Twig_Filter('html_colour', 'html_colour'),
            new Twig_Filter('country_name', 'get_country_name'),
            new Twig_Filter('first_paragraph', 'first_paragraph'),
            new Twig_Filter('byte_symbol', 'byte_symbol'),
            new Twig_Filter('html_link', 'html_link'),
            new Twig_Filter('parse_line', 'parse_line'),
            new Twig_Filter('parse_text', 'parse_text'),
            new Twig_Filter('asset_url', 'asset_url'),
            new Twig_Filter('perms_check', 'perms_check'),
            new Twig_Filter('bg_settings', 'user_background_settings_strings'),
            new Twig_Filter('colour_contrast', 'colour_get_css_contrast'),
            new Twig_Filter('colour_props', 'colour_get_properties'),
            new Twig_Filter('log_format', function (string $text, string $json): string {
                return vsprintf($text, json_decode($json));
            }),
        ];
    }

    public function getFunctions()
    {
        return [
            new Twig_Function('get_browser', 'get_browser'),
            new Twig_Function('git_commit_hash', 'git_commit_hash'),
            new Twig_Function('git_tag', 'git_tag'),
            new Twig_Function('csrf_token', 'csrf_token'),
            new Twig_Function('csrf_input', 'csrf_html'),
            new Twig_Function('sql_query_count', 'db_query_count'),
            new Twig_Function('url_construct', 'url_construct'),
            new Twig_Function('warning_has_duration', 'user_warning_has_duration'),
            new Twig_Function('get_csrf_tokens', 'csrf_get_list'),
            new Twig_Function('startup_time', function (float $time = MSZ_STARTUP) {
                return microtime(true) - $time;
            }),
        ];
    }
}
