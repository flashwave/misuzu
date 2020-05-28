<?php
namespace Misuzu\Users;

use Misuzu\DB;

class UserChatTokenException extends UsersException {}
class UserChatTokenNotFoundException extends UserChatTokenException {}
class UserChatTokenCreationFailedException extends UserChatTokenException {}

class UserChatToken {
    // Database fields
    private $user_id = -1;
    private $token_string = '';
    private $token_created = null;

    private $user = null;

    public const TOKEN_WIDTH = 32;
    public const TOKEN_LIFETIME = 60 * 60 * 24 * 7;

    public const TABLE = 'user_chat_tokens';
    private const QUERY_SELECT = 'SELECT %1$s FROM `' . DB::PREFIX . self::TABLE . '` AS '. self::TABLE;
    private const SELECT = '%1$s.`user_id`, %1$s.`token_string`'
        . ', UNIX_TIMESTAMP(%1$s.`token_created`) AS `token_created`';

    public function getUserId(): int {
        return $this->user_id < 1 ? -1 : $this->user_id;
    }
    public function getUser(): User {
        if($this->user === null)
            $this->user = User::byId($this->getUserId());
        return $this->user;
    }

    public function getToken(): string {
        return $this->token_string;
    }

    public function getCreatedTime(): int {
        return $this->token_created === null ? -1 : $this->token_created;
    }
    public function getExpirationTime(): int {
        return $this->getCreatedTime() + self::TOKEN_LIFETIME;
    }
    public function hasExpired(): bool {
        return $this->getExpirationTime() <= time();
    }

    public function delete(): void {
        DB::prepare('
            DELETE FROM `msz_user_chat_tokens`
            WHERE `user_id` = :user,
            AND `token_string` = :token
        ')->bind('user', $this->getUserId())
          ->bind('token', $this->getToken())
          ->execute();
    }

    public static function generateToken(): string {
        return bin2hex(random_bytes(self::TOKEN_WIDTH));
    }

    public static function create(User $user): self {
        $token = self::generateToken();
        $create = DB::prepare('
            INSERT INTO `msz_user_chat_tokens` (`user_id`, `token_string`)
            VALUES (:user, :token)
        ')  ->bind('user', $user->getId())
            ->bind('token', $token)
            ->execute();

        if(!$create)
            throw new UserChatTokenCreationFailedException;

        return self::byExact($user, $token);
    }

    private static function byQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf(self::SELECT, self::TABLE));
    }
    public static function byExact(User $user, string $token): self {
        $tokenInfo = DB::prepare(self::byQueryBase() . ' WHERE `user_id` = :user AND `token_string` = :token')
            ->bind('user', $user->getId())
            ->bind('token', $token)
            ->fetchObject(self::class);

        if(!$tokenInfo)
            throw new UserChatTokenNotFoundException;

        return $tokenInfo;
    }
}
