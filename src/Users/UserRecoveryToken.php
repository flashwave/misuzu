<?php
namespace Misuzu\Users;

use Misuzu\DB;
use Misuzu\Net\IPAddress;

class UserRecoveryTokenException extends UsersException {}
class UserRecoveryTokenNotFoundException extends UserRecoveryTokenException {}
class UserRecoveryTokenCreationFailedException extends UserRecoveryTokenException {}

class UserRecoveryToken {
    // Database fields
    private $user_id = -1;
    private $reset_ip = '::1';
    private $reset_requested = null;
    private $verification_code = null;

    private $user = null;

    public const TOKEN_WIDTH = 6;
    public const TOKEN_LIFETIME = 60 * 60;

    public const TABLE = 'users_password_resets';
    private const QUERY_SELECT = 'SELECT %1$s FROM `' . DB::PREFIX . self::TABLE . '` AS '. self::TABLE;
    private const SELECT = '%1$s.`user_id`, %1$s.`verification_code`'
        . ', INET6_NTOA(%1$s.`reset_ip`) AS `reset_ip`'
        . ', UNIX_TIMESTAMP(%1$s.`reset_requested`) AS `reset_requested`';

    public function getUserId(): int {
        return $this->user_id < 1 ? -1 : $this->user_id;
    }
    public function getUser(): User {
        if($this->user === null)
            $this->user = User::byId($this->getUserId());
        return $this->user;
    }

    public function getRemoteAddress(): string {
        return $this->reset_ip;
    }

    public function getToken(): string {
        return $this->verification_code ?? '';
    }
    public function hasToken(): bool {
        return !empty($this->verification_code);
    }

    public function getCreationTime(): int {
        return $this->reset_requested === null ? -1 : $this->reset_requested;
    }
    public function getExpirationTime(): int {
        return $this->getCreationTime() + self::TOKEN_LIFETIME;
    }
    public function hasExpired(): bool {
        return $this->getExpirationTime() <= time();
    }

    public function isValid(): bool {
        return $this->hasToken() && !$this->hasExpired();
    }

    public function invalidate(): void {
        DB::prepare(
            'UPDATE `' . DB::PREFIX . self::TABLE . '` SET `verification_code` = NULL'
            . ' WHERE `verification_code` = :token AND `user_id` = :user'
        )   ->bind('token', $this->verification_code)
            ->bind('user', $this->user_id)
            ->execute();
    }

    public static function generateToken(): string {
        return bin2hex(random_bytes(self::TOKEN_WIDTH));
    }

    public static function create(User $user, ?string $remoteAddr = null, bool $return = true): ?self {
        $remoteAddr = $remoteAddr ?? IPAddress::remote();
        $token = self::generateToken();

        $created = DB::prepare('INSERT INTO `' . DB::PREFIX . self::TABLE . '` (`user_id`, `reset_ip`, `verification_code`) VALUES (:user, INET6_ATON(:address), :token)')
            ->bind('user', $user->getId())
            ->bind('address', $remoteAddr)
            ->bind('token', $token)
            ->execute();

        if(!$created)
            throw new UserRecoveryTokenCreationFailedException;
        if(!$return)
            return null;

        try {
            $object = self::byToken($token);
            $object->user = $user;
            return $object;
        } catch(UserRecoveryTokenNotFoundException $ex) {
            throw new UserRecoveryTokenCreationFailedException;
        }
    }

    private static function byQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf(self::SELECT, self::TABLE));
    }
    public static function byToken(string $token): self {
        $object = DB::prepare(self::byQueryBase() . ' WHERE `verification_code` = :token')
            ->bind('token', $token)
            ->fetchObject(self::class);

        if(!$object)
            throw new UserRecoveryTokenNotFoundException;

        return $object;
    }
    public static function byUserAndRemoteAddress(User $user, ?string $remoteAddr = null): self {
        $remoteAddr = $remoteAddr ?? IPAddress::remote();
        $object = DB::prepare(self::byQueryBase() . ' WHERE `user_id` = :user AND `reset_ip` = INET6_ATON(:address)')
            ->bind('user', $user->getId())
            ->bind('address', $remoteAddr)
            ->fetchObject(self::class);

        if(!$object)
            throw new UserRecoveryTokenNotFoundException;

        return $object;
    }
}
