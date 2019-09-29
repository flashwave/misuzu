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
            INSERT INTO `msz_users` (
                `username`, `password`, `email`, `register_ip`,
                `last_ip`, `user_country`, `display_role`
            ) VALUES (
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
        return DB::prepare(self::USER_SELECT . 'WHERE `user_id` = :user_id')
            ->bind('user_id', $userId)
            ->fetchObject(User::class);
    }

    public static function findForLogin(string $usernameOrEmail): ?User {
        return DB::prepare(self::USER_SELECT . 'WHERE LOWER(`email`) = LOWER(:email) OR LOWER(`username`) = LOWER(:username)')
            ->bind('email', $usernameOrEmail)
            ->bind('username', $usernameOrEmail)
            ->fetchObject(User::class);
    }

    public function hasUserId(): bool {
        return isset($this->user_id) && $this->user_id > 0;
    }

    public function hasPassword(): bool {
        return !empty($this->password);
    }
    public function checkPassword(string $password): bool {
        return $this->hasPassword() && password_verify($password, $this->password);
    }
    public function passwordNeedsRehash(): bool {
        return password_needs_rehash($this->password, MSZ_USERS_PASSWORD_HASH_ALGO);
    }
    public function setPassword(string $password): void {
        if(!$this->hasUserId())
            return;

        DB::prepare('UPDATE `msz_users` SET `password` = :password WHERE `user_id` = :user_id')
            ->bind('password', password_hash($password, MSZ_USERS_PASSWORD_HASH_ALGO))
            ->bind('user_id', $this->user_id)
            ->execute();
    }

    public function isDeleted(): bool {
        return !empty($this->user_deleted);
    }

    public function hasTOTP(): bool {
        return !empty($this->user_totp_key);
    }
}
