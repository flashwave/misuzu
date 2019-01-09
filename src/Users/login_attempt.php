<?php
function user_login_attempt_record(bool $success, ?int $userId, string $ipAddress, string $userAgent): void
{
    $storeAttempt = db_prepare('
        INSERT INTO `msz_login_attempts`
            (`attempt_success`, `attempt_ip`, `attempt_country`, `user_id`, `attempt_user_agent`)
        VALUES
            (:attempt_success, INET6_ATON(:attempt_ip), :attempt_country, :user_id, :attempt_user_agent)
    ');

    $storeAttempt->bindValue('attempt_success', $success ? 1 : 0);
    $storeAttempt->bindValue('attempt_ip', $ipAddress);
    $storeAttempt->bindValue('attempt_country', ip_country_code($ipAddress));
    $storeAttempt->bindValue('attempt_user_agent', $userAgent);
    $storeAttempt->bindValue('user_id', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $storeAttempt->execute();
}

function user_login_attempts_remaining(string $ipAddress): int
{
    $getRemaining = db_prepare('
        SELECT 5 - COUNT(`attempt_id`)
        FROM `msz_login_attempts`
        WHERE `attempt_success` = 0
        AND `attempt_created` > NOW() - INTERVAL 1 HOUR
        AND `attempt_ip` = INET6_ATON(:remote_ip)
    ');
    $getRemaining->bindValue('remote_ip', $ipAddress);

    return $getRemaining->execute()
        ? (int)$getRemaining->fetchColumn()
        : 0;
}

function user_login_attempts_count($userId = 0): int
{
    $getCount = db_prepare(sprintf('
        SELECT COUNT(`attempt_id`)
        FROM `msz_login_attempts`
        WHERE %s
    ', $userId < 1 ? '1' : '`user_id` = :user_id'));

    if ($userId >= 1) {
        $getCount->bindValue('user_id', $userId);
    }

    return $getCount->execute() ? (int)$getCount->fetchColumn() : 0;
}

function user_login_attempts_list(int $offset, int $take, int $userId = 0): array
{
    $offset = max(0, $offset);
    $take = max(1, $take);

    $getAttempts = db_prepare(sprintf('
        SELECT
            `attempt_id`, `attempt_country`, `attempt_success`, `attempt_user_agent`, `attempt_created`,
            INET6_NTOA(`attempt_ip`) as `attempt_ip`
        FROM `msz_login_attempts`
        WHERE %s
        ORDER BY `attempt_id` DESC
        LIMIT :offset, :take
    ', $userId < 1 ? '1' : '`user_id` = :user_id'));

    if ($userId > 0) {
        $getAttempts->bindValue('user_id', $userId);
    }

    $getAttempts->bindValue('offset', $offset);
    $getAttempts->bindValue('take', $take);

    return db_fetch_all($getAttempts);
}
