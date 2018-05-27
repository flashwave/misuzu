<?php
use Misuzu\Database;

define('MSZ_SESSION_KEY_SIZE', 64);

function user_session_create(
    int $userId,
    string $ipAddress,
    string $userAgent
): string {
    $sessionKey = user_session_generate_key();

    $createSession = Database::connection()->prepare('
        INSERT INTO `msz_sessions`
            (
                `user_id`, `session_ip`, `session_country`,
                `user_agent`, `session_key`, `created_at`, `expires_on`
            )
        VALUES
            (
                :user_id, INET6_ATON(:session_ip), :session_country,
                :user_agent, :session_key, NOW(), NOW() + INTERVAL 1 MONTH
            )
    ');
    $createSession->bindValue('user_id', $userId);
    $createSession->bindValue('session_ip', $ipAddress);
    $createSession->bindValue('session_country', get_country_code($ipAddress));
    $createSession->bindValue('user_agent', $userAgent);
    $createSession->bindValue('session_key', $sessionKey);

    return $createSession->execute() ? $sessionKey : '';
}

function user_session_delete(int $sessionId): bool
{
    $deleteSession = Database::connection()->prepare('
        DELETE FROM `msz_sessions`
        WHERE `session_id` = :session_id
    ');
    $deleteSession->bindValue('session_id', $sessionId);
    return $deleteSession->execute();
}

function user_session_generate_key(): string
{
    return bin2hex(random_bytes(MSZ_SESSION_KEY_SIZE / 2));
}
