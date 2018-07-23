<?php
use Misuzu\Database;
use Misuzu\Net\IPAddress;

function audit_log(
    string $action,
    int $userId = 0,
    array $params = [],
    IPAddress $ipAddress = null
): void {
    $ipAddress = $ipAddress ?? IPAddress::remote();

    for ($i = 0; $i < count($params); $i++) {
        if (preg_match('#^(-?[0-9]+)$#', $params[$i])) {
            $params[$i] = (int)$params[$i];
        }
    }

    $addLog = Database::prepare('
        INSERT INTO `msz_audit_log`
            (`log_action`, `user_id`, `log_params`, `log_ip`, `log_country`)
        VALUES
            (:action, :user, :params, :ip, :country)
    ');
    $addLog->bindValue('action', $action);
    $addLog->bindValue('user', $userId < 1 ? null : $userId);
    $addLog->bindValue('params', json_encode($params));
    $addLog->bindValue('ip', $ipAddress->getRaw());
    $addLog->bindValue('country', $ipAddress->getCountryCode());
    $addLog->execute();
}

function audit_log_count($userId = 0): int
{
    $getCount = Database::prepare(sprintf('
        SELECT COUNT(`log_id`)
        FROM `msz_audit_log`
        WHERE %s
    ', $userId < 1 ? '1' : '`user_id` = :user_id'));

    if ($userId >= 1) {
        $getCount->bindValue('user_id', $userId);
    }

    return $getCount->execute() ? (int)$getCount->fetchColumn() : 0;
}

function audit_log_list(int $offset, int $take, int $userId = 0): array
{
    $offset = max(0, $offset);
    $take = max(1, $take);

    $getLogs = Database::prepare(sprintf('
        SELECT
            l.`log_id`, l.`log_action`, l.`log_params`, l.`log_created`, l.`log_country`,
            u.`user_id`, u.`username`,
            INET6_NTOA(l.`log_ip`) as `log_ip`,
            COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`
        FROM `msz_audit_log` as l
        LEFT JOIN `msz_users` as u
        ON u.`user_id` = l.`user_id`
        LEFT JOIN `msz_roles` as r
        ON r.`role_id` = u.`display_role`
        WHERE %s
        ORDER BY l.`log_id` DESC
        LIMIT :offset, :take
    ', $userId < 1 ? '1' : 'l.`user_id` = :user_id'));

    if ($userId >= 1) {
        $getLogs->bindValue('user_id', $userId);
    }

    $getLogs->bindValue('offset', $offset);
    $getLogs->bindValue('take', $take);

    return $getLogs->execute() ? $getLogs->fetchAll(PDO::FETCH_ASSOC) : [];
}
