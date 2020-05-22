<?php
namespace Misuzu\Users;

use Misuzu\DB;
use InvalidArgumentException;
use RuntimeException;

final class ChatToken {
    public const TOKEN_LIFETIME = 60 * 60 * 24 * 7;

    public function getUserId(): int {
        return $this->user_id ?? 0;
    }

    public function getToken(): string {
        return $this->token_string ?? '';
    }

    public function getCreationTime(): int {
        return $this->token_created ?? 0;
    }
    public function getExpirationTime(): int {
        return $this->getCreationTime() + self::TOKEN_LIFETIME;
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

    public static function create(int $userId): self {
        if($userId < 1)
            throw new InvalidArgumentException('Invalid user id.');

        $token = bin2hex(random_bytes(32));
        $create = DB::prepare('
            INSERT INTO `msz_user_chat_tokens` (`user_id`, `token_string`)
            VALUES (:user, :token)
        ')->bind('user', $userId)->bind('token', $token)->execute();

        if(!$create)
            throw new RuntimeException('Token creation failed.');

        return self::get($userId, $token);
    }

    public static function get(int $userId, string $token): self {
        if($userId < 1)
            throw new InvalidArgumentException('Invalid user id.');
        if(strlen($token) !== 64)
            throw new InvalidArgumentException('Invalid token string.');

        $token = DB::prepare('
            SELECT `user_id`, `token_string`, UNIX_TIMESTAMP(`token_created`) AS `token_created`
            FROM `msz_user_chat_tokens`
            WHERE `user_id` = :user
            AND `token_string` = :token
        ')->bind('user', $userId)->bind('token', $token)->fetchObject(self::class);

        if(empty($token))
            throw new RuntimeException('Token not found.');

        return $token;
    }
}
