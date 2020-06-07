<?php
namespace Misuzu\Comments;

use Misuzu\DB;
use Misuzu\Users\User;
use Misuzu\Users\UserNotFoundException;

class CommentsParser {
    private const MARKUP_USERNAME = '#\B(?:@{1}(' . User::NAME_REGEX . '))#u';
    private const MARKUP_USERID = '#\B(?:@{2}([0-9]+))#u';

    public static function parseForStorage(string $text): string {
        return preg_replace_callback(self::MARKUP_USERNAME, function ($matches) {
            try {
                return sprintf('@@%d', User::byUsername($matches[1])->getId());
            } catch(UserNotFoundException $ex) {
                return $matches[0];
            }
        }, $text);
    }

    public static function parseForDisplay(string $text): string {
        $text = htmlentities($text);

        $text = preg_replace_callback(
            '/(^|[\n ])([\w]*?)([\w]*?:\/\/[\w]+[^ \,\"\n\r\t<]*)/is',
            function ($matches) {
                $matches[0] = trim($matches[0]);
                $url = parse_url($matches[0]);
                if(empty($url['scheme']) || !in_array(mb_strtolower($url['scheme']), ['http', 'https'], true))
                    return $matches[0];
                return sprintf(' <a href="%1$s" class="link" target="_blank" rel="noreferrer noopener">%1$s</a>', $matches[0]);
            },
            $text
        );

        $text = preg_replace_callback(self::MARKUP_USERID, function ($matches) {
            $getInfo = DB::prepare('
                SELECT
                    u.`user_id`, u.`username`,
                    COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`
                FROM `msz_users` as u
                LEFT JOIN `msz_roles` as r
                ON u.`display_role` = r.`role_id`
                WHERE `user_id` = :user_id
            ');
            $getInfo->bind('user_id', $matches[1]);
            $info = $getInfo->fetch();

            if(empty($info))
                return $matches[0];

            return sprintf(
                '<a href="%s" class="comment__mention", style="%s">@%s</a>',
                url('user-profile', ['user' => $info['user_id']]),
                html_colour($info['user_colour']),
                $info['username']
            );
        }, $text);

        return nl2br($text);
    }
}
