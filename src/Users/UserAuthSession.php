<?php
namespace Misuzu\Users;

use Misuzu\DB;

class UserAuthSessionException extends UsersException {}
class UserAuthSessionNotFoundException extends UserAuthSessionException {}
class UserAuthSessionCreationFailedException extends UserAuthSessionException {}

class UserAuthSession {
    // Database fields
    private $user_id = -1;
    private $tfa_token = '';
    private $tfa_created = null;

    private $user = null;

    public const TOKEN_WIDTH = 16;
    public const TOKEN_LIFETIME = 60 * 15;

    public const TABLE = 'auth_tfa';
    private const QUERY_SELECT = 'SELECT %1$s FROM `' . DB::PREFIX . self::TABLE . '` AS '. self::TABLE;
    private const SELECT = '%1$s.`user_id`, %1$s.`tfa_token`'
        . ', UNIX_TIMESTAMP(%1$s.`tfa_created`) AS `tfa_created`';

    public function getUserId(): int {
        return $this->user_id < 1 ? -1 : $this->user_id;
    }
    public function getUser(): User {
        if($this->user === null)
            $this->user = User::byId($this->getUserId());
        return $this->user;
    }

    public function getToken(): string {
        return $this->tfa_token;
    }

    public function getCreationTime(): int {
        return $this->tfa_created === null ? -1 : $this->tfa_created;
    }
    public function getExpirationTime(): int {
        return $this->getCreationTime() + self::TOKEN_LIFETIME;
    }
    public function hasExpired(): bool {
        return $this->getExpirationTime() <= time();
    }

    public function delete(): void {
        DB::prepare('DELETE FROM `' . DB::PREFIX . self::TABLE . '` WHERE `tfa_token` = :token')
            ->bind('token', $this->tfa_token)
            ->execute();
    }

    public static function generateToken(): string {
        return bin2hex(random_bytes(self::TOKEN_WIDTH));
    }

    public static function create(User $user, bool $return = true): ?self {
        $token = self::generateToken();
        $created = DB::prepare('INSERT INTO `' . DB::PREFIX . self::TABLE . '` (`user_id`, `tfa_token`) VALUES (:user, :token)')
            ->bind('user', $user->getId())
            ->bind('token', $token)
            ->execute();

        if(!$created)
            throw new UserAuthSessionCreationFailedException;
        if(!$return)
            return null;

        try {
            $object = self::byToken($token);
            $object->user = $user;
            return $object;
        } catch(UserAuthSessionNotFoundException $ex) {
            throw new UserAuthSessionCreationFailedException;
        }
    }

    private static function byQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf(self::SELECT, self::TABLE));
    }
    public static function byToken(string $token): self {
        $object = DB::prepare(self::byQueryBase() . ' WHERE `tfa_token` = :token')
            ->bind('token', $token)
            ->fetchObject(self::class);

        if(!$object)
            throw new UserAuthSessionNotFoundException;

        return $object;
    }
}
