<?php
define('MSZ_WARN_NOTE', 0);
define('MSZ_WARN_WARNING', 1);
define('MSZ_WARN_SILENCE', 2);
define('MSZ_WARN_BAN', 3);

define('MSZ_WARN_TYPES', [
    MSZ_WARN_NOTE, MSZ_WARN_WARNING, MSZ_WARN_SILENCE, MSZ_WARN_BAN,
]);

define('MSZ_WARN_TYPES_PUBLIC', [
    MSZ_WARN_WARNING,
    MSZ_WARN_SILENCE,
    MSZ_WARN_BAN,
]);

define('MSZ_WARN_TYPE_NAMES', [
    MSZ_WARN_NOTE => 'Note',
    MSZ_WARN_WARNING => 'Warning',
    MSZ_WARN_SILENCE => 'Silence',
    MSZ_WARN_BAN => 'Ban',
]);

function user_warning_type_is_valid(int $type): bool
{
    return in_array($type, MSZ_WARN_TYPES, true);
}

function user_warning_type_get_name(int $type): string
{
    return user_warning_type_is_valid($type) ? MSZ_WARN_TYPE_NAMES[$type] : '';
}

function user_warning_get_types(): array
{
    return MSZ_WARN_TYPE_NAMES;
}

function user_warning_is_public(int $type): bool
{
    return in_array($type, MSZ_WARN_TYPES_PUBLIC, true);
}

function user_warning_add(
    int $userId,
    string $userIp,
    int $issuerId,
    string $issuerIp,
    int $type,
    string $publicNote,
    string $privateNote
): int {
    if (!in_array($type, MSZ_WARN_TYPES, true)) {
        return -1;
    }

    if ($userId < 1) {
        return -2;
    }

    $addWarning = db_prepare('
        INSERT INTO `msz_user_warnings`
            (`user_id`, `user_ip`, `issuer_id`, `issuer_ip`, `warning_type`, `warning_note`, `warning_note_private`)
        VALUES
            (:user_id, INET6_ATON(:user_ip), :issuer_id, INET6_ATON(:issuer_ip), :type, :note, :note_private)
    ');
    $addWarning->bindValue('user_id', $userId);
    $addWarning->bindValue('user_ip', $userIp);
    $addWarning->bindValue('issuer_id', $issuerId);
    $addWarning->bindValue('issuer_ip', $issuerIp);
    $addWarning->bindValue('type', $type);
    $addWarning->bindValue('note', $publicNote);
    $addWarning->bindValue('note_private', $privateNote);

    if (!$addWarning->execute()) {
        return 0;
    }

    return (int)db_last_insert_id();
}

function user_warning_count(int $userId): int
{
    if ($userId < 1) {
        return 0;
    }

    $countWarnings = db_prepare('
        SELECT COUNT(`warning_id`)
        FROM `msz_user_warnings`
        WHERE `user_id` = :user_id
    ');
    $countWarnings->bindValue('user_id', $userId);
    return (int)($countWarnings->execute() ? $countWarnings->fetchColumn() : 0);
}

function user_warning_remove(int $warningId): bool
{
    if ($warningId < 1) {
        return false;
    }

    $removeWarning = db_prepare('
        DELETE FROM `msz_user_warnings`
        WHERE `warning_id` = :warning_id
    ');
    $removeWarning->bindValue('warning_id', $warningId);
    return $removeWarning->execute();
}

function user_warning_fetch(
    int $userId,
    ?int $days = null
): array {
    $fetchWarnings = db_prepare(sprintf(
        '
            SELECT
                uw.`warning_id`, uw.`warning_created`, uw.`warning_type`, uw.`warning_note`,
                uw.`warning_note_private`, uw.`user_id`, uw.`issuer_id`, uw.`warning_duration`,
                TIMESTAMPDIFF(SECOND, uw.`warning_created`, uw.`warning_duration`) AS `warning_duration_secs`,
                INET6_NTOA(uw.`user_ip`) AS `user_ip`, INET6_NTOA(uw.`issuer_ip`) AS `issuer_ip`,
                iu.`username` AS `issuer_username`
            FROM `msz_user_warnings` AS uw
            LEFT JOIN `msz_users` AS iu
            ON iu.`user_id` = uw.`issuer_id`
            WHERE uw.`user_id` = :user_id
            %s
            ORDER BY uw.`warning_id` DESC
        ',
        $days !== null ? 'AND uw.`warning_created` >= NOW() - INTERVAL :days DAY' : ''
    ));
    $fetchWarnings->bindValue('user_id', $userId);

    if ($days !== null) {
        $fetchWarnings->bindValue('days', $days);
    }

    $warnings = $fetchWarnings->execute() ? $fetchWarnings->fetchAll(PDO::FETCH_ASSOC) : false;
    return $warnings ? $warnings : [];
}

function user_warning_global_count(): int
{
    $countWarnings = db_query('
        SELECT COUNT(`warning_id`)
        FROM `msz_user_warnings`
    ');
    return (int)$countWarnings->fetchColumn();
}

function user_warning_global_fetch(int $offset = 0, int $take = 50, ?int $userId = null): array
{
    $fetchWarnings = db_prepare(sprintf(
        '
            SELECT
                uw.`warning_id`, uw.`warning_created`, uw.`warning_type`, uw.`warning_note`,
                uw.`warning_note_private`, uw.`user_id`, uw.`issuer_id`, uw.`warning_duration`,
                TIMESTAMPDIFF(SECOND, uw.`warning_created`, uw.`warning_duration`) AS `warning_duration_secs`,
                INET6_NTOA(uw.`user_ip`) AS `user_ip`, INET6_NTOA(uw.`issuer_ip`) AS `issuer_ip`,
                iu.`username` AS `issuer_username`, wu.`username` AS `username`
            FROM `msz_user_warnings` AS uw
            LEFT JOIN `msz_users` AS iu
            ON iu.`user_id` = uw.`issuer_id`
            LEFT JOIN `msz_users` AS wu
            ON wu.`user_id` = uw.`user_id`
            %1$s
            ORDER BY uw.`warning_id` DESC
            LIMIT :offset, :take
        ',
        $userId > 0 ? 'WHERE uw.`user_id` = :user_id' : ''
    ));
    $fetchWarnings->bindValue('offset', $offset);
    $fetchWarnings->bindValue('take', $take);

    if ($userId > 0) {
        $fetchWarnings->bindValue('user_id', $userId);
    }

    $warnings = $fetchWarnings->execute() ? $fetchWarnings->fetchAll(PDO::FETCH_ASSOC) : false;
    return $warnings ? $warnings : [];
}
