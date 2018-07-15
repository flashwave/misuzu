<?php
use Misuzu\Database;

function user_login_attempt_record(bool $success, ?int $userId, string $ipAddress, string $userAgent): void
{
    $storeAttempt = Database::prepare('
        INSERT INTO `msz_login_attempts`
            (`was_successful`, `attempt_ip`, `attempt_country`, `user_id`, `user_agent`, `created_at`)
        VALUES
            (:was_successful, INET6_ATON(:attempt_ip), :attempt_country, :user_id, :user_agent, NOW())
    ');

    $storeAttempt->bindValue('was_successful', $success ? 1 : 0);
    $storeAttempt->bindValue('attempt_ip', $ipAddress);
    $storeAttempt->bindValue('attempt_country', get_country_code($ipAddress));
    $storeAttempt->bindValue('user_agent', $userAgent);
    $storeAttempt->bindValue('user_id', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $storeAttempt->execute();
}

function user_login_attempts_remaining(string $ipAddress): int
{
    $getRemaining = Database::prepare('
        SELECT 5 - COUNT(`attempt_id`)
        FROM `msz_login_attempts`
        WHERE `was_successful` = false
        AND `created_at` > NOW() - INTERVAL 1 HOUR
        AND `attempt_ip` = INET6_ATON(:remote_ip)
    ');
    $getRemaining->bindValue('remote_ip', $ipAddress);

    return $getRemaining->execute()
        ? (int)$getRemaining->fetchColumn()
        : 0;
}
