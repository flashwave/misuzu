<?php
namespace Misuzu\Users;

use Misuzu\DB;

class User {
    private const USER_SELECT = '
        SELECT  `user_id`, `username`, `password`, `email`, `user_super`, `user_title`,
                `user_country`, `user_colour`, `display_role`, `user_totp_key`,
                `user_about_content`, `user_about_parser`,
                `user_signature_content`, `user_signature_parser`,
                `user_birthdate`, `user_background_settings`,
                INET6_NTOA(`register_ip`)       AS `register_ip`,
                INET6_NTOA(`last_ip`)           AS `last_ip`,
                UNIX_TIMESTAMP(`user_created`)  AS `user_created`,
                UNIX_TIMESTAMP(`user_active`)   AS `user_active`,
                UNIX_TIMESTAMP(`user_deleted`)  AS `user_deleted`,
                `user_website`, `user_twitter`, `user_github`, `user_skype`,
                `user_discord`, `user_youtube`, `user_steam`, `user_ninswitch`,
                `user_twitchtv`, `user_osu`, `user_lastfm`
        FROM    `msz_users`
    ';

    public function __construct() {
        //
    }

    public static function create(
        string $username,
        string $password,
        string $email,
        string $ipAddress
    ): ?User {
        $createUser = DB::prepare('
            INSERT INTO `msz_users`
                (
                    `username`, `password`, `email`, `register_ip`,
                    `last_ip`, `user_country`, `display_role`
                )
            VALUES
                (
                    :username, :password, LOWER(:email), INET6_ATON(:register_ip),
                    INET6_ATON(:last_ip), :user_country, 1
                )
        ')  ->bind('username', $username)->bind('email', $email)
            ->bind('register_ip', $ipAddress)->bind('last_ip', $ipAddress)
            ->bind('password', user_password_hash($password))
            ->bind('user_country', ip_country_code($ipAddress))
            ->executeGetId();

        if($createUser < 1)
            return null;

        return static::get($createUser);
    }

    public static function get(int $userId): ?User {
        return DB::prepare(self::USER_SELECT . 'WHERE   `user_id` = :user_id')
            ->bind('user_id', $userId)
            ->fetchObject(User::class);
    }
}
