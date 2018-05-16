<?php
use Misuzu\Database;

function user_login_attempt_record(bool $success, ?int $userId, string $ipAddress, string $userAgent): void
{
    $storeAttempt = Database::connection()->prepare('
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
