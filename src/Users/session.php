<?php
define('MSZ_SESSION_KEY_SIZE', 64);

function user_session_create(
    int $userId,
    string $ipAddress,
    string $userAgent
): string {
    $sessionKey = user_session_generate_key();

    $createSession = \Misuzu\DB::prepare('
        INSERT INTO `msz_sessions`
            (
                `user_id`, `session_ip`, `session_country`,
                `session_user_agent`, `session_key`, `session_created`, `session_expires`
            )
        VALUES
            (
                :user_id, INET6_ATON(:session_ip), :session_country,
                :session_user_agent, :session_key, NOW(), NOW() + INTERVAL 1 MONTH
            )
    ');
    $createSession->bind('user_id', $userId);
    $createSession->bind('session_ip', $ipAddress);
    $createSession->bind('session_country', ip_country_code($ipAddress));
    $createSession->bind('session_user_agent', $userAgent);
    $createSession->bind('session_key', $sessionKey);

    return $createSession->execute() ? $sessionKey : '';
}

function user_session_find($sessionId, bool $byKey = false): array {
    if(!$byKey && $sessionId < 1) {
        return [];
    }

    $findSession = \Misuzu\DB::prepare(sprintf('
        SELECT
            `session_id`, `user_id`,
            INET6_NTOA(`session_ip`) as `session_ip`,
            INET6_NTOA(`session_ip_last`) as `session_ip_last`,
            `session_country`, `session_user_agent`, `session_key`, `session_created`,
            `session_expires`, `session_active`, `session_expires_bump`
        FROM `msz_sessions`
        WHERE `%s` = :session_id
    ', $byKey ? 'session_key' : 'session_id'));
    $findSession->bind('session_id', $sessionId);
    return $findSession->fetch();
}

function user_session_delete(int $sessionId): void {
    $deleteSession = \Misuzu\DB::prepare('
        DELETE FROM `msz_sessions`
        WHERE `session_id` = :session_id
    ');
    $deleteSession->bind('session_id', $sessionId);
    $deleteSession->execute();
}

function user_session_generate_key(): string {
    return bin2hex(random_bytes(MSZ_SESSION_KEY_SIZE / 2));
}

function user_session_purge_all(int $userId): void {
    \Misuzu\DB::prepare('
        DELETE FROM `msz_sessions`
        WHERE `user_id` = :user_id
    ')->execute([
        'user_id' => $userId,
    ]);
}

function user_session_count($userId = 0): int {
    $getCount = \Misuzu\DB::prepare(sprintf('
        SELECT COUNT(`session_id`)
        FROM `msz_sessions`
        %s
    ', $userId < 1 ? '' : 'WHERE `user_id` = :user_id'));

    if($userId >= 1) {
        $getCount->bind('user_id', $userId);
    }

    return (int)$getCount->fetchColumn();
}

function user_session_list(int $offset, int $take, int $userId = 0): array {
    $offset = max(0, $offset);
    $take = max(1, $take);

    $getSessions = \Misuzu\DB::prepare(sprintf('
        SELECT
            `session_id`, `session_country`, `session_user_agent`, `session_created`,
            `session_expires`, `session_active`, `session_expires_bump`,
            INET6_NTOA(`session_ip`) as `session_ip`,
            INET6_NTOA(`session_ip_last`) as `session_ip_last`
        FROM `msz_sessions`
        WHERE %s
        ORDER BY `session_id` DESC
        LIMIT :offset, :take
    ', $userId < 1 ? '1' : '`user_id` = :user_id'));

    if($userId > 0) {
        $getSessions->bind('user_id', $userId);
    }

    $getSessions->bind('offset', $offset);
    $getSessions->bind('take', $take);

    return $getSessions->fetchAll();
}

function user_session_bump_active(int $sessionId, string $ipAddress = null): void {
    if($sessionId < 1) {
        return;
    }

    $bump = \Misuzu\DB::prepare('
        UPDATE `msz_sessions`
        SET `session_active` = NOW(),
            `session_ip_last` = INET6_ATON(:last_ip),
            `session_expires` = IF(`session_expires_bump`, NOW() + INTERVAL 1 MONTH, `session_expires`)
        WHERE `session_id` = :session_id
    ');
    $bump->bind('session_id', $sessionId);
    $bump->bind('last_ip', $ipAddress ?? ip_remote_address());
    $bump->execute();
}

// the functions below this line are imperative

function user_session_data(?array $newData = null): array {
    static $data = [];

    if(!is_null($newData)) {
        $data = $newData;
    }

    return $data;
}

function user_session_start(int $userId, string $sessionKey): bool {
    $session = user_session_find($sessionKey, true);

    if(!$session || $session['user_id'] !== $userId) {
        return false;
    }

    if(time() >= strtotime($session['session_expires'])) {
        user_session_delete($session['session_id']);
        return false;
    }

    user_session_data($session);
    return true;
}

function user_session_stop(bool $delete = false): void {
    if(empty(user_session_data())) {
        return;
    }

    if($delete) {
        user_session_delete(user_session_data()['session_id']);
    }

    user_session_data([]);
}

function user_session_current(?string $variable = null, $default = null) {
    if(empty($variable)) {
        return user_session_data() ?? [];
    }

    return user_session_data()[$variable] ?? $default;
}

function user_session_active(): bool {
    return !empty(user_session_data())
        && time() < strtotime(user_session_data()['session_expires']);
}

define('MSZ_SESSION_COOKIE_VERSION', 1);
// make sure to match this to the final fixed size of the cookie string
// it'll pad older tokens out for backwards compatibility
define('MSZ_SESSION_COOKIE_SIZE', 37);

function user_session_cookie_pack(int $userId, string $sessionToken): ?string {
    if(strlen($sessionToken) !== MSZ_SESSION_KEY_SIZE) {
        return null;
    }

    return pack('CNH64', MSZ_SESSION_COOKIE_VERSION, $userId, $sessionToken);
}

function user_session_cookie_unpack(string $packed): array {
    $packed = str_pad($packed, MSZ_SESSION_COOKIE_SIZE, "\x00");
    $unpacked = unpack('Cversion/Nuser/H64token', $packed);

    if($unpacked['version'] < 1 || $unpacked['version'] > MSZ_SESSION_COOKIE_VERSION) {
        return [];
    }

    // Make sure this contains all fields with a default for version > 1 exclusive stuff
    $data = [
        'user_id' => $unpacked['user'],
        'session_token' => $unpacked['token'],
    ];

    return $data;
}
