<?php
define('MSZ_USER_RECOVERY_TOKEN_LENGTH', 6); // * 2

function user_recovery_token_sent(int $userId, string $ipAddress): bool {
    $tokenSent = \Misuzu\DB::prepare('
        SELECT COUNT(`verification_code`) > 0
        FROM `msz_users_password_resets`
        WHERE `user_id` = :user
        AND `reset_ip` = INET6_ATON(:ip)
        AND `reset_requested` > NOW() - INTERVAL 1 HOUR
        AND `verification_code` IS NOT NULL
    ');

    $tokenSent->bind('user', $userId);
    $tokenSent->bind('ip', $ipAddress);

    return (bool)$tokenSent->fetchColumn();
}

function user_recovery_token_validate(int $userId, string $token): bool {
    $validateToken = \Misuzu\DB::prepare('
        SELECT COUNT(`user_id`) > 0
        FROM `msz_users_password_resets`
        WHERE `user_id` = :user
        AND `verification_code` = :code
        AND `verification_code` IS NOT NULL
        AND `reset_requested` > NOW() - INTERVAL 1 HOUR
    ');

    $validateToken->bind('user', $userId);
    $validateToken->bind('code', $token);

    return (bool)$validateToken->fetchColumn();
}

function user_recovery_token_generate(): string {
    return bin2hex(random_bytes(MSZ_USER_RECOVERY_TOKEN_LENGTH));
}

function user_recovery_token_create(int $userId, string $ipAddress): string {
    $code = user_recovery_token_generate();

    $insertResetKey = \Misuzu\DB::prepare('
        REPLACE INTO `msz_users_password_resets`
            (`user_id`, `reset_ip`, `verification_code`)
        VALUES
            (:user, INET6_ATON(:ip), :code)
    ');
    $insertResetKey->bind('user', $userId);
    $insertResetKey->bind('ip', $ipAddress);
    $insertResetKey->bind('code', $code);

    return $insertResetKey->execute() ? $code : '';
}

function user_recovery_token_invalidate(int $userId, string $token): void {
    $invalidateCode = \Misuzu\DB::prepare('
        UPDATE `msz_users_password_resets`
        SET `verification_code` = NULL
        WHERE `verification_code` = :code
        AND `user_id` = :user
    ');

    $invalidateCode->bind('user', $userId);
    $invalidateCode->bind('code', $token);
    $invalidateCode->execute();
}
