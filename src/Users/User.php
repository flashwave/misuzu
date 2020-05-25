<?php
namespace Misuzu\Users;

use Misuzu\Colour;
use Misuzu\DB;
use Misuzu\Memoizer;
use Misuzu\TOTP;
use Misuzu\Net\IPAddress;

class UserException extends UsersException {} // this naming definitely won't lead to confusion down the line!
class UserNotFoundException extends UserException {}

class User {
    // Database fields
    // TODO: update all references to use getters and setters and mark all of these as private
    public $user_id = -1;
    public $username = '';
    public $password = '';
    public $email = '';
    public $register_ip = '::1';
    public $last_ip = '::1';
    public $user_super = 0;
    public $user_country = 'XX';
    public $user_colour = null;
    public $user_created = null;
    public $user_active = null;
    public $user_deleted = null;
    public $display_role = 1;
    public $user_totp_key = null;
    public $user_about_content = null;
    public $user_about_parser = 0;
    public $user_signature_content = null;
    public $user_signature_parser = 0;
    public $user_birthdate = '';
    public $user_background_settings = 0;
    public $user_title = null;

    private static $localUser = null;

    private $totp = null;

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

    public function getId(): int {
        return $this->user_id < 1 ? -1 : $this->user_id;
    }

    public function getUsername(): string {
        return $this->username;
    }

    public function getColour(): Colour {
        return new Colour($this->getColourRaw());
    }
    public function getColourRaw(): int {
        return $this->user_colour ?? 0x40000000;
    }

    public function getEmailAddress(): string {
        return $this->email;
    }

    public function getHierarchy(): int {
        return ($userId = $this->getId()) < 1 ? 0 : user_get_hierarchy($userId);
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
        if(($userId = $this->getId()) < 1)
            return;

        DB::prepare('UPDATE `msz_users` SET `password` = :password WHERE `user_id` = :user_id')
            ->bind('password', password_hash($password, MSZ_USERS_PASSWORD_HASH_ALGO))
            ->bind('user_id', $userId)
            ->execute();
    }

    public function isDeleted(): bool {
        return !empty($this->user_deleted);
    }

    public function getDisplayRoleId(): int {
        return $this->display_role < 1 ? -1 : $this->display_role;
    }

    public function hasTOTP(): bool {
        return !empty($this->user_totp_key);
    }
    public function getTOTP(): TOTP {
        if($this->totp === null)
            $this->totp = new TOTP($this->user_totp_key);
        return $this->totp;
    }
    public function getValidTOTPTokens(): array {
        if(!$this->hasTOTP())
            return [];
        $totp = $this->getTOTP();
        return [
            $totp->generate(time()),
            $totp->generate(time() - 30),
            $totp->generate(time() + 30),
        ];
    }

    public function getBackgroundSettings(): int { // Use the below methods instead
        return $this->user_background_settings;
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

    public function getTitle(): string {
        return $this->user_title;
    }

    public function profileFields(bool $filterEmpty = true): array {
        if(($userId = $this->getId()) < 1)
            return [];
        return ProfileField::user($userId, $filterEmpty);
    }

    // TODO: Is this the proper location/implementation for this? (no)
    private $commentPermsArray = null;
    public function commentPerms(): array {
        if($this->commentPermsArray === null)
            $this->commentPermsArray = perms_check_user_bulk(MSZ_PERMS_COMMENTS, $this->getId(), [
                'can_comment' => MSZ_PERM_COMMENTS_CREATE,
                'can_delete' => MSZ_PERM_COMMENTS_DELETE_OWN | MSZ_PERM_COMMENTS_DELETE_ANY,
                'can_delete_any' => MSZ_PERM_COMMENTS_DELETE_ANY,
                'can_pin' => MSZ_PERM_COMMENTS_PIN,
                'can_lock' => MSZ_PERM_COMMENTS_LOCK,
                'can_vote' => MSZ_PERM_COMMENTS_VOTE,
            ]);
        return $this->commentPermsArray;
    }

    public function setCurrent(): void {
        self::$localUser = $this;
    }
    public static function unsetCurrent(): void {
        self::$localUser = null;
    }
    public static function getCurrent(): ?User {
        return self::$localUser;
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

        return static::byId($createUser);
    }

    private static function getMemoizer() {
        static $memoizer = null;
        if($memoizer === null)
            $memoizer = new Memoizer;
        return $memoizer;
    }

    public static function byId(int $userId): ?User {
        return self::getMemoizer()->find($userId, function() use ($userId) {
            $user = DB::prepare(self::USER_SELECT . 'WHERE `user_id` = :user_id')
                ->bind('user_id', $userId)
                ->fetchObject(User::class);
            if(!$user)
                throw new UserNotFoundException;
            return $user;
        });
    }
    public static function findForLogin(string $usernameOrEmail): ?User {
        $usernameOrEmailLower = mb_strtolower($usernameOrEmail);
        return self::getMemoizer()->find(function($user) use ($usernameOrEmailLower) {
            return mb_strtolower($user->getUsername())     === $usernameOrEmailLower
                || mb_strtolower($user->getEmailAddress()) === $usernameOrEmailLower;
        }, function() use ($usernameOrEmail) {
            $user = DB::prepare(self::USER_SELECT . 'WHERE LOWER(`email`) = LOWER(:email) OR LOWER(`username`) = LOWER(:username)')
                ->bind('email', $usernameOrEmail)
                ->bind('username', $usernameOrEmail)
                ->fetchObject(User::class);
            if(!$user)
                throw new UserNotFoundException;
            return $user;
        });
    }
    public static function findForProfile($userIdOrName): ?User {
        $userIdOrNameLower = mb_strtolower($userIdOrName);
        return self::getMemoizer()->find(function($user) use ($userIdOrNameLower) {
            return $user->getId() == $userIdOrNameLower || mb_strtolower($user->getUsername()) === $userIdOrNameLower;
        }, function() use ($userIdOrName) {
            $user = DB::prepare(self::USER_SELECT . 'WHERE `user_id` = :user_id OR LOWER(`username`) = LOWER(:username)')
                ->bind('user_id', (int)$userIdOrName)
                ->bind('username', (string)$userIdOrName)
                ->fetchObject(User::class);
            if(!$user)
                throw new UserNotFoundException;
            return $user;
        });
    }
}
