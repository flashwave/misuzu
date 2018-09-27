<?php
use Misuzu\Database;

define('MSZ_SESSION_KEY_SIZE', 64);

function user_session_create(
    int $userId,
    string $ipAddress,
    string $userAgent
): string {
    $sessionKey = user_session_generate_key();

    $createSession = Database::prepare('
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
    $createSession->bindValue('session_country', ip_country_code($ipAddress));
    $createSession->bindValue('user_agent', $userAgent);
    $createSession->bindValue('session_key', $sessionKey);

    return $createSession->execute() ? $sessionKey : '';
}

function user_session_find(int $sessionId): array
{
    if ($sessionId < 1) {
        return [];
    }

    $findSession = Database::prepare('
        SELECT
            `session_id`, `user_id`, INET6_NTOA(`session_ip`) as `session_ip`,
            `session_country`, `user_agent`, `session_key`, `created_at`, `expires_on`
        FROM `msz_sessions`
        WHERE `session_id` = :session_id
    ');
    $findSession->bindValue('session_id', $sessionId);
    $session = $findSession->execute() ? $findSession->fetch(PDO::FETCH_ASSOC) : false;
    return $session ? $session : [];
}

function user_session_delete(int $sessionId): bool
{
    $deleteSession = Database::prepare('
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

function user_session_purge_all(int $userId): void
{
    Database::prepare('
        DELETE FROM `msz_sessions`
        WHERE `user_id` = :user_id
    ')->execute([
        'user_id' => $userId,
    ]);
}
