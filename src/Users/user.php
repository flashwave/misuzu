<?php
use Misuzu\Database;

define('MSZ_USERS_PASSWORD_HASH_ALGO', PASSWORD_ARGON2I);

function user_create(
    string $username,
    string $password,
    string $email,
    string $ipAddress
): int {
    $dbc = Database::connection();
    $createUser = $dbc->prepare('
        INSERT INTO `msz_users`
            (
                `username`, `password`, `email`, `register_ip`,
                `last_ip`, `user_country`, `created_at`, `display_role`
            )
        VALUES
            (
                :username, :password, :email, INET6_ATON(:register_ip),
                INET6_ATON(:last_ip), :user_country, NOW(), 1
            )
    ');
    $createUser->bindValue('username', $username);
    $createUser->bindValue('password', user_password_hash($password));
    $createUser->bindValue('email', $email);
    $createUser->bindValue('register_ip', $ipAddress);
    $createUser->bindValue('last_ip', $ipAddress);
    $createUser->bindValue('user_country', get_country_code($ipAddress));

    return $createUser->execute() ? (int)$dbc->lastInsertId() : 0;
}

function user_password_hash(string $password): string
{
    return password_hash($password, MSZ_USERS_PASSWORD_HASH_ALGO);
}

// Temporary key generation for chat login.
// Should eventually be replaced with a callback login system.
function user_generate_chat_key(int $userId): string
{
    $chatKey = bin2hex(random_bytes(16));

    $setChatKey = Database::connection()->prepare('
        UPDATE `msz_users`
        SET `user_chat_key` = :user_chat_key
        WHERE `user_id` = :user_id
    ');
    $setChatKey->bindValue('user_chat_key', $chatKey);
    $setChatKey->bindValue('user_id', $userId);

    return $setChatKey->execute() ? $chatKey : '';
}
