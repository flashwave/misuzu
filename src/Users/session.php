<?php
use Misuzu\Database;

define('MSZ_SESSION_DATA_STORE', '_msz_user_session_data');
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

function user_session_find($sessionId, bool $byKey = false): array
{
    if (!$byKey && $sessionId < 1) {
        return [];
    }

    $findSession = Database::prepare(sprintf('
        SELECT
            `session_id`, `user_id`, INET6_NTOA(`session_ip`) as `session_ip`,
            `session_country`, `user_agent`, `session_key`, `created_at`, `expires_on`
        FROM `msz_sessions`
        WHERE `%s` = :session_id
    ', $byKey ? 'session_key' : 'session_id'));
    $findSession->bindValue('session_id', $sessionId);
    $session = $findSession->execute() ? $findSession->fetch(PDO::FETCH_ASSOC) : false;
    return $session ? $session : [];
}

function user_session_delete(int $sessionId): void
{
    $deleteSession = Database::prepare('
        DELETE FROM `msz_sessions`
        WHERE `session_id` = :session_id
    ');
    $deleteSession->bindValue('session_id', $sessionId);
    $deleteSession->execute();
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

// the functions below this line are imperative

function user_session_start(int $userId, string $sessionKey): bool
{
    $session = user_session_find($sessionKey, true);

    if (!$session
        || $session['user_id'] !== $userId) {
        return false;
    }

    if (time() >= strtotime($session['expires_on'])) {
        user_session_delete($session['session_id']);
        return false;
    }

    $GLOBALS[MSZ_SESSION_DATA_STORE] = $session;
    return true;
}

function user_session_stop(bool $delete = false): void
{
    if (empty($GLOBALS[MSZ_SESSION_DATA_STORE])) {
        return;
    }

    if ($delete) {
        user_session_delete($GLOBALS[MSZ_SESSION_DATA_STORE]['session_id']);
    }

    $GLOBALS[MSZ_SESSION_DATA_STORE] = [];
}

function user_session_current(?string $variable = null, $default = null)
{
    if (empty($variable)) {
        return $GLOBALS[MSZ_SESSION_DATA_STORE] ?? [];
    }

    return $GLOBALS[MSZ_SESSION_DATA_STORE][$variable] ?? $default;
}

function user_session_active(): bool
{
    return !empty($GLOBALS[MSZ_SESSION_DATA_STORE])
        && time() < strtotime($GLOBALS[MSZ_SESSION_DATA_STORE]['expires_on']);
}
