<?php
namespace Misuzu\Users;

use Misuzu\Colour;
use Misuzu\DB;
use Misuzu\Net\IPAddress;

class User {
    private const USER_SELECT = '
        SELECT u.`user_id`, u.`username`, u.`password`, u.`email`, u.`user_super`, u.`user_title`,
               u.`user_country`, u.`user_colour`, u.`display_role`, u.`user_totp_key`,
               u.`user_about_content`, u.`user_about_parser`,
               u.`user_signature_content`, u.`user_signature_parser`,
               u.`user_birthdate`, u.`user_background_settings`,
               INET6_NTOA(u.`register_ip`) AS `register_ip`, INET6_NTOA(u.`last_ip`) AS `last_ip`,
               UNIX_TIMESTAMP(u.`user_created`) AS `user_created`, UNIX_TIMESTAMP(u.`user_active`) AS `user_active`,
               UNIX_TIMESTAMP(u.`user_deleted`) AS `user_deleted`,
               COALESCE(u.`user_title`, r.`role_title`) AS `user_title`,
               COALESCE(u.`user_colour`, r.`role_colour`) AS `user_colour`,
               TIMESTAMPDIFF(YEAR, IF(u.`user_birthdate` < \'0001-01-01\', NULL, u.`user_birthdate`), NOW()) AS `user_age`
        FROM `msz_users` AS u
        LEFT JOIN `msz_roles` AS r
        ON r.`role_id` = u.`display_role`
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
            ->bind('user_country', IPAddress::country($ipAddress))
            ->executeGetId();

        if($createUser < 1)
            return null;

        return static::get($createUser);
    }

    public static function get(int $userId): ?User { return self::byId($userId); }
    public static function byId(int $userId): ?User {
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
    public static function findForProfile($userId): ?User {
        return DB::prepare(self::USER_SELECT . 'WHERE `user_id` = :user_id OR LOWER(`username`) = LOWER(:username)')
            ->bind('user_id', (int)$userId)
            ->bind('username', (string)$userId)
            ->fetchObject(User::class);
    }

    public function hasUserId(): bool { return $this->hasId(); }
    public function getUserId(): int { return $this->getId(); }
    public function hasId(): bool {
        return isset($this->user_id) && $this->user_id > 0;
    }
    public function getId(): int {
        return $this->user_id ?? 0;
    }

    public function hasUsername(): bool {
        return isset($this->username);
    }
    public function getUsername(): string {
        return $this->username ?? '';
    }

    public function hasColour(): bool {
        return isset($this->user_colour);
    }
    public function getColour(): Colour {
        return new Colour($this->getColourRaw());
    }
    public function getColourRaw(): int {
        return $this->user_colour ?? 0x40000000;
    }

    public function getHierarchy(): int {
        return $this->hasUserId() ? user_get_hierarchy($this->getUserId()) : 0;
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

    public function getBackgroundAttachment(): int {
        return $this->user_background_settings & 0x0F;
    }
    public function getBackgroundBlend(): bool {
        return ($this->user_background_settings & MSZ_USER_BACKGROUND_ATTRIBUTE_BLEND) > 0;
    }
    public function getBackgroundSlide(): bool {
        return ($this->user_background_settings & MSZ_USER_BACKGROUND_ATTRIBUTE_SLIDE) > 0;
    }

    public function profileFields(bool $filterEmpty = true): array {
        if(!$this->hasUserId())
            return [];

        return ProfileField::user($this->user_id, $filterEmpty);
    }
}
