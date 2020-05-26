<?php
define('MSZ_WARN_NOTE', 0);
define('MSZ_WARN_WARNING', 1);
define('MSZ_WARN_SILENCE', 2);
define('MSZ_WARN_BAN', 3);

define('MSZ_WARN_TYPES', [
    MSZ_WARN_NOTE, MSZ_WARN_WARNING, MSZ_WARN_SILENCE, MSZ_WARN_BAN,
]);

define('MSZ_WARN_TYPES_HAS_DURATION', [
    MSZ_WARN_SILENCE, MSZ_WARN_BAN,
]);

define('MSZ_WARN_TYPES_VISIBLE_TO_STAFF', MSZ_WARN_TYPES);
define('MSZ_WARN_TYPES_VISIBLE_TO_USER', [
    MSZ_WARN_WARNING, MSZ_WARN_SILENCE, MSZ_WARN_BAN,
]);
define('MSZ_WARN_TYPES_VISIBLE_TO_PUBLIC', [
    MSZ_WARN_SILENCE, MSZ_WARN_BAN,
]);

define('MSZ_WARN_TYPE_NAMES', [
    MSZ_WARN_NOTE => 'Note',
    MSZ_WARN_WARNING => 'Warning',
    MSZ_WARN_SILENCE => 'Silence',
    MSZ_WARN_BAN => 'Ban',
]);

function user_warning_type_is_valid(int $type): bool {
    return in_array($type, MSZ_WARN_TYPES, true);
}

function user_warning_type_get_name(int $type): string {
    return user_warning_type_is_valid($type) ? MSZ_WARN_TYPE_NAMES[$type] : '';
}

function user_warning_get_types(): array {
    return MSZ_WARN_TYPE_NAMES;
}

function user_warning_has_duration(int $type): bool {
    return in_array($type, MSZ_WARN_TYPES_HAS_DURATION, true);
}

define('MSZ_E_WARNING_ADD_DB', -1);
define('MSZ_E_WARNING_ADD_TYPE', -2);
define('MSZ_E_WARNING_ADD_USER', -3);
define('MSZ_E_WARNING_ADD_DURATION', -4);

function user_warning_add(
    int $userId,
    string $userIp,
    int $issuerId,
    string $issuerIp,
    int $type,
    string $publicNote,
    string $privateNote,
    ?int $duration = null
): int {
    if(!user_warning_type_is_valid($type))
        return MSZ_E_WARNING_ADD_TYPE;

    if($userId < 1)
        return MSZ_E_WARNING_ADD_USER;

    if(user_warning_has_duration($type)) {
        if($duration <= time())
            return MSZ_E_WARNING_ADD_DURATION;
    } else
        $duration = 0;

    $addWarning = \Misuzu\DB::prepare('
        INSERT INTO `msz_user_warnings`
            (`user_id`, `user_ip`, `issuer_id`, `issuer_ip`, `warning_type`, `warning_note`, `warning_note_private`, `warning_duration`)
        VALUES
            (:user_id, INET6_ATON(:user_ip), :issuer_id, INET6_ATON(:issuer_ip), :type, :note, :note_private, :duration)
    ');
    $addWarning->bind('user_id', $userId);
    $addWarning->bind('user_ip', $userIp);
    $addWarning->bind('issuer_id', $issuerId);
    $addWarning->bind('issuer_ip', $issuerIp);
    $addWarning->bind('type', $type);
    $addWarning->bind('note', $publicNote);
    $addWarning->bind('note_private', $privateNote);
    $addWarning->bind('duration', $duration < 1 ? null : date('Y-m-d H:i:s', $duration));

    if(!$addWarning->execute())
        return MSZ_E_WARNING_ADD_DB;

    return \Misuzu\DB::lastId();
}

function user_warning_count(int $userId): int {
    if($userId < 1)
        return 0;

    $countWarnings = \Misuzu\DB::prepare('
        SELECT COUNT(`warning_id`)
        FROM `msz_user_warnings`
        WHERE `user_id` = :user_id
    ');
    $countWarnings->bind('user_id', $userId);
    return (int)$countWarnings->fetchColumn(0, 0);
}

function user_warning_remove(int $warningId): bool {
    if($warningId < 1)
        return false;

    $removeWarning = \Misuzu\DB::prepare('
        DELETE FROM `msz_user_warnings`
        WHERE `warning_id` = :warning_id
    ');
    $removeWarning->bind('warning_id', $warningId);
    return $removeWarning->execute();
}

function user_warning_fetch(
    int $userId,
    ?int $days = null,
    array $displayTypes = MSZ_WARN_TYPES
): array {
    $fetchWarnings = \Misuzu\DB::prepare(sprintf(
        '
            SELECT
                uw.`warning_id`, uw.`warning_created`, uw.`warning_type`, uw.`warning_note`,
                uw.`warning_note_private`, uw.`user_id`, uw.`issuer_id`, uw.`warning_duration`,
                INET6_NTOA(uw.`user_ip`) AS `user_ip`, INET6_NTOA(uw.`issuer_ip`) AS `issuer_ip`,
                iu.`username` AS `issuer_username`
            FROM `msz_user_warnings` AS uw
            LEFT JOIN `msz_users` AS iu
            ON iu.`user_id` = uw.`issuer_id`
            WHERE uw.`user_id` = :user_id
            AND uw.`warning_type` IN (%1$s)
            %2$s
            ORDER BY uw.`warning_id` DESC
        ',
        implode(',', array_apply($displayTypes, 'intval')),
        $days !== null ? 'AND (uw.`warning_created` >= NOW() - INTERVAL :days DAY OR (uw.`warning_duration` IS NOT NULL AND uw.`warning_duration` > NOW()))' : ''
    ));
    $fetchWarnings->bind('user_id', $userId);

    if($days !== null)
        $fetchWarnings->bind('days', $days);

    return $fetchWarnings->fetchAll();
}

function user_warning_global_count(?int $userId = null): int {
    $countWarnings = \Misuzu\DB::prepare(sprintf('
        SELECT COUNT(`warning_id`)
        FROM `msz_user_warnings`
        %s
    ', $userId > 0 ? 'WHERE `user_id` = :user_id' : ''));

    if($userId > 0)
        $countWarnings->bind('user_id', $userId);

    return (int)$countWarnings->fetchColumn(0, 0);
}

function user_warning_global_fetch(int $offset = 0, int $take = 50, ?int $userId = null): array {
    $fetchWarnings = \Misuzu\DB::prepare(sprintf(
        '
            SELECT
                uw.`warning_id`, uw.`warning_created`, uw.`warning_type`, uw.`warning_note`,
                uw.`warning_note_private`, uw.`user_id`, uw.`issuer_id`, uw.`warning_duration`,
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
    $fetchWarnings->bind('offset', $offset);
    $fetchWarnings->bind('take', $take);

    if($userId > 0)
        $fetchWarnings->bind('user_id', $userId);

    return $fetchWarnings->fetchAll();
}

function user_warning_check_ip(string $address): bool {
    $checkAddress = \Misuzu\DB::prepare(sprintf(
        '
            SELECT COUNT(`warning_id`) > 0
            FROM `msz_user_warnings`
            WHERE `warning_type` IN (%s)
            AND `user_ip` = INET6_ATON(:address)
            AND `warning_duration` IS NOT NULL
            AND `warning_duration` >= NOW()
        ',
        implode(',', MSZ_WARN_TYPES_HAS_DURATION)
    ));
    $checkAddress->bind('address', $address);
    return (bool)$checkAddress->fetchColumn(0, false);
}

function user_warning_check_expiration(int $userId, int $type): int {
    if($userId < 1 || !user_warning_has_duration($type))
        return 0;

    static $memo = [];
    $memoId = "{$userId}-{$type}";

    if(array_key_exists($memoId, $memo))
        return $memo[$memoId];

    $getExpiration = \Misuzu\DB::prepare('
        SELECT `warning_duration`
        FROM `msz_user_warnings`
        WHERE `warning_type` = :type
        AND `user_id` = :user
        AND `warning_duration` IS NOT NULL
        AND `warning_duration` >= NOW()
        ORDER BY `warning_duration` DESC
        LIMIT 1
    ');
    $getExpiration->bind('type', $type);
    $getExpiration->bind('user', $userId);
    $expiration = $getExpiration->fetchColumn(0, '');

    return $memo[$memoId] = (empty($expiration) ? 0 : strtotime($expiration));
}

function user_warning_check_restriction(int $userId): bool {
    if($userId < 1)
        return false;

    static $memo = [];

    if(array_key_exists($userId, $memo))
        return $memo[$userId];

    $checkAddress = \Misuzu\DB::prepare(sprintf(
        '
            SELECT COUNT(`warning_id`) > 0
            FROM `msz_user_warnings`
            WHERE `warning_type` IN (%s)
            AND `user_id` = :user
            AND `warning_duration` IS NOT NULL
            AND `warning_duration` >= NOW()
        ',
        implode(',', MSZ_WARN_TYPES_HAS_DURATION)
    ));
    $checkAddress->bind('user', $userId);
    return $memo[$userId] = (bool)$checkAddress->fetchColumn(0, false);
}
